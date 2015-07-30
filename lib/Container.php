<?php

namespace PhpBench;

use PhpBench\Console\Application;
use PhpBench\Report\Generator\ConsoleTableGenerator;
use PhpBench\Console\Command\ReportCommand;
use PhpBench\ProgressLogger\DotsProgressLogger;
use PhpBench\Console\Command\RunCommand;
use PhpBench\Extension;
use PhpBench\Result\Dumper\XmlDumper;
use PhpBench\Report\ReportManager;
use PhpBench\ProgressLoggerRegistry;
use PhpBench\Benchmark\Runner;
use PhpBench\Benchmark\CollectionBuilder;
use Symfony\Component\Finder\Finder;
use PhpBench\Benchmark\SubjectBuilder;
use PhpBench\Result\Loader\XmlLoader;
use PhpBench\Extension\CoreExtension;

/**
 * PHPBench Container.
 *
 * This is a simple, extendable, closure based dependency injection container.
 */
class Container
{
    private $instantiators = array();
    private $services = array();
    private $tags = array();
    private $parameters = array();
    private $extensions = array();

    public function __construct()
    {
        // Add the core extension by deefault
        $this->parameters['extensions'] = array(
            'PhpBench\Extension\CoreExtension',
        );
    }

    /**
     * Configure the container. This method will call the `configure()` method
     * on each extension. Extensions must use this opportunity to register their
     * services and define any default parameters.
     *
     * This method must be called before `build()`.
     */
    public function configure()
    {
        foreach ($this->parameters['extensions'] as $extensionClass) {
            if (!class_exists($extensionClass)) {
                throw new \InvalidArgumentException(sprintf(
                    'Extension class "%s" does not exist',
                    $extensionClass
                ));
            }

            $extension = new $extensionClass();

            if (!$extension instanceof Extension) {
                throw new \InvalidArgumentException(sprintf(
                    'Extensions "%s" must implement the PhpBench\\Extension interface',
                    get_class($extension)
                ));
            }

            $extension->configure($this);
            $this->extensions[] = $extension;
        }
    }

    /**
     * Build the container. This method will call the `build()` method on each registered extension.
     * The extensions can use this as a "compiler pass" to add tagged services to other services for example.
     */
    public function build()
    {
        foreach ($this->extensions as $extension) {
            $extension->build($this);
        }
    }

    /**
     * Instantiate and return the service with the given ID.
     * Note that this method will return the same instance on subsequent calls.
     *
     * @param string $serviceId
     * @return mixed
     */
    public function get($serviceId)
    {
        if (isset($this->services[$serviceId])) {
            return $this->services[$serviceId];
        }

        if (!isset($this->instantiators[$serviceId])) {
            throw new \InvalidArgumentException(sprintf(
                'No instantiator has been registered for requested service "%s"',
                $serviceId
            ));
        }

        $this->services[$serviceId] = $this->instantiators[$serviceId]($this);

        return $this->services[$serviceId];
    }

    /**
     * Set a service instance
     *
     * @param string $serviceId
     * @param mixed $instance
     */
    public function set($serviceId, $instance)
    {
        $this->services[$serviceId] = $instance;
    }

    /**
     * Return services IDs for the given tag
     *
     * @param string $tag
     * @return string[]
     */
    public function getServiceIdsForTag($tag)
    {
        $serviceIds = array();
        foreach ($this->tags as $serviceId => $tags) {
            if (isset($tags[$tag])) {
                $serviceIds[$serviceId] = $tags[$tag];
            }
        }

        return $serviceIds;
    }

    /**
     * Register a service with the given ID and instantiator.
     *
     * The instantiator is a closure which accepts an instance of this container and
     * returns a new instance of the service class.
     *
     * @param string $serviceId
     * @param \Closure $instantiator
     * @param string[] $tags
     */
    public function register($serviceId, \Closure $instantiator, array $tags = array())
    {
        if (isset($this->instantiators[$serviceId])) {
            throw new \InvalidArgumentException(sprintf(
                'Service with ID "%s" has already been registered'
            ));
        }

        $this->instantiators[$serviceId] = $instantiator;
        $this->tags[$serviceId] = $tags;
    }

    /**
     * Merge an array of parameters onto the existing parameter set.
     *
     * @param array $parameters
     */
    public function mergeParameters(array $parameters)
    {
        $this->parameters = array_merge(
            $this->parameters,
            $parameters
        );
    }

    /**
     * Return the parameter with the given name.
     *
     * @param string $name
     * @return mixed
     * @throws \InvalidArgumentException
     */
    public function getParameter($name)
    {
        if (!array_key_exists($name, $this->parameters)) {
            throw new \InvalidArgumentException(sprintf(
                'Parameter "%s" has not been registered',
                $name
            ));
        }

        return $this->parameters[$name];
    }
}