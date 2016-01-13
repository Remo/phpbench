<?php

/*
 * This file is part of the PHPBench package
 *
 * (c) Daniel Leech <daniel@dantleech.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpBench\Benchmark;

use PhpBench\Benchmark\Metadata\BenchmarkMetadata;
use PhpBench\Benchmark\Metadata\SubjectMetadata;
use PhpBench\PhpBench;
use PhpBench\Progress\Logger\NullLogger;
use PhpBench\Progress\LoggerInterface;
use PhpBench\Registry\Config;
use PhpBench\Registry\Registry;
use PhpBench\Util\TimeUnit;
use PhpBench\Vcs\DetectorInterface;

/**
 * The benchmark runner.
 */
class Runner
{
    private $logger;
    private $collectionBuilder;
    private $configPath;
    private $retryThreshold = null;
    private $executorRegistry;
    private $vcsDetector;

    /**
     * @param CollectionBuilder $collectionBuilder
     * @param SubjectBuilder $subjectBuilder
     * @param string $configPath
     */
    public function __construct(
        CollectionBuilder $collectionBuilder,
        Registry $executorRegistry,
        DetectorInterface $vcsDetector,
        $retryThreshold,
        $configPath
    ) {
        $this->logger = new NullLogger();
        $this->collectionBuilder = $collectionBuilder;
        $this->executorRegistry = $executorRegistry;
        $this->vcsDetector = $vcsDetector;
        $this->configPath = $configPath;
        $this->retryThreshold = $retryThreshold;
    }

    /**
     * Set the progress logger to use.
     *
     * @param LoggerInterface
     */
    public function setProgressLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Run all benchmarks (or all applicable benchmarks) in the given path.
     *
     * The $name argument will set the "name" attribute on the "suite" element.
     *
     * @param string $contextName
     * @param string $path
     */
    public function run(RunnerContext $context)
    {
        $executorConfig = $this->executorRegistry->getConfig($context->getExecutor());
        $executor = $this->executorRegistry->getService($executorConfig['executor']);

        $dom = new SuiteDocument();
        $rootEl = $dom->createElement('phpbench');
        $rootEl->setAttribute('version', PhpBench::VERSION);
        $dom->appendChild($rootEl);

        $suiteEl = $rootEl->appendElement('suite');
        $suiteEl->setAttribute('context', $context->getContextName());
        $suiteEl->setAttribute('date', date('c'));
        $suiteEl->setAttribute('config-path', $this->configPath);
        $suiteEl->setAttribute('retry-threshold', $context->getRetryThreshold($this->retryThreshold));
        $envEl = $suiteEl->appendElement('env');
        $unameEl = $envEl->appendElement('unname');
        $vcsInfo = $this->vcsDetector->getVcsInformation();
        foreach (array(
            'os' => 's',
            'host' => 'n',
            'release' => 'r',
            'version' => 'v',
            'machine' => 'm',
        ) as $key => $mode) {
            $unameEl->setAttribute($key, php_uname($mode));
        }
        $vcsEl = $envEl->appendElement('vcs');
        $vcsEl->setAttribute('system', $vcsInfo->getSystem());
        $vcsEl->setAttribute('branch', $vcsInfo->getBranch());
        $vcsEl->setAttribute('version', $vcsInfo->getVersion());
        $phpEl = $envEl->appendElement('php');
        $phpEl->setAttribute('version', PHP_VERSION);

        if (!stristr(PHP_OS, 'win')) {
            $load = sys_getloadavg();
            $loadEl = $envEl->appendElement('sysload');
            $loadEl->setAttribute('m1', $load[0]);
            $loadEl->setAttribute('m5', $load[1]);
            $loadEl->setAttribute('m15', $load[2]);
        }


        $collection = $this->collectionBuilder->buildCollection($context->getPath(), $context->getFilters(), $context->getGroups());

        $this->logger->startSuite($dom);

        /* @var BenchmarkMetadata */
        foreach ($collection->getBenchmarks() as $benchmark) {
            $benchmarkEl = $dom->createElement('benchmark');
            $benchmarkEl->setAttribute('class', $benchmark->getClass());

            $this->logger->benchmarkStart($benchmark);
            $this->runBenchmark($executor, $context, $benchmark, $benchmarkEl);
            $this->logger->benchmarkEnd($benchmark);

            $suiteEl->appendChild($benchmarkEl);
        }

        $this->logger->endSuite($dom);

        return $dom;
    }

    private function runBenchmark(
        ExecutorInterface $executor,
        RunnerContext $context,
        BenchmarkMetadata $benchmark,
        \DOMElement $benchmarkEl
    ) {
        if ($benchmark->getBeforeClassMethods()) {
            $executor->executeMethods($benchmark, $benchmark->getBeforeClassMethods());
        }

        foreach ($benchmark->getSubjectMetadatas() as $subject) {
            $subjectEl = $benchmarkEl->appendElement('subject');
            $subjectEl->setAttribute('name', $subject->getName());

            if (true === $subject->getSkip()) {
                continue;
            }

            foreach ($subject->getGroups() as $group) {
                $groupEl = $subjectEl->appendElement('group');
                $groupEl->setAttribute('name', $group);
            }

            $this->logger->subjectStart($subject);
            $this->runSubject($executor, $context, $subject, $subjectEl);
            $this->logger->subjectEnd($subject);
        }

        if ($benchmark->getAfterClassMethods()) {
            $executor->executeMethods($benchmark, $benchmark->getAfterClassMethods());
        }
    }

    private function runSubject(ExecutorInterface $executor, RunnerContext $context, SubjectMetadata $subject, \DOMElement $subjectEl)
    {
        $parameterSets = $context->getParameterSets($subject->getParameterSets());
        $paramsIterator = new CartesianParameterIterator($parameterSets);

        foreach ($paramsIterator as $parameterSet) {
            $variantEl = $subjectEl->ownerDocument->createElement('variant');
            $variantEl->setAttribute('sleep', $context->getSleep($subject->getSleep()));
            $variantEl->setAttribute('output-time-unit', $subject->getOutputTimeUnit() ?: TimeUnit::MICROSECONDS);
            $variantEl->setAttribute('output-mode', $subject->getOutputMode() ?: TimeUnit::MODE_TIME);
            $variantEl->setAttribute('revs', $context->getRevolutions($subject->getRevs()));
            foreach ($parameterSet as $name => $value) {
                $parameterEl = $this->createParameter($subjectEl, $name, $value);
                $variantEl->appendChild($parameterEl);
            }

            $subjectEl->appendChild($variantEl);
            $this->runIterations($executor, $context, $subject, $parameterSet, $variantEl);
        }
    }

    private function createParameter($parentEl, $name, $value)
    {
        $parameterEl = $parentEl->ownerDocument->createElement('parameter');
        $parameterEl->setAttribute('name', $name);

        if (is_array($value)) {
            $parameterEl->setAttribute('type', 'collection');
            foreach ($value as $key => $element) {
                $childEl = $this->createParameter($parameterEl, $key, $element);
                $parameterEl->appendChild($childEl);
            }

            return $parameterEl;
        }

        if (is_scalar($value)) {
            $parameterEl->setAttribute('value', $value);

            return $parameterEl;
        }

        throw new \InvalidArgumentException(sprintf(
            'Parameters must be either scalars or arrays, got: %s',
            is_object($value) ? get_class($value) : gettype($value)
        ));
    }

    private function runIterations(ExecutorInterface $executor, RunnerContext $context, SubjectMetadata $subject, ParameterSet $parameterSet, \DOMElement $variantEl)
    {
        $iterationCount = $context->getIterations($subject->getIterations());
        $revolutionCount = $context->getRevolutions($subject->getRevs());
        $executorConfig = $this->executorRegistry->getConfig($context->getExecutor());

        $iterationCollection = new IterationCollection(
            $subject,
            $parameterSet,
            $iterationCount,
            $revolutionCount,
            $context->getRetryThreshold($this->retryThreshold)
        );

        $this->logger->iterationsStart($iterationCollection);

        try {
            foreach ($iterationCollection as $iteration) {
                $this->runIteration($executor, $executorConfig, $iteration, $context->getSleep($subject->getSleep()));
            }
        } catch (\Exception $e) {
            $iterationCollection->setException($e);
            $this->logger->iterationsEnd($iterationCollection);
            $this->appendException($variantEl, $e);

            return;
        }

        $iterationCollection->computeStats();
        $this->logger->iterationsEnd($iterationCollection);

        while ($iterationCollection->getRejectCount() > 0) {
            $this->logger->retryStart($iterationCollection->getRejectCount());
            $this->logger->iterationsStart($iterationCollection);
            foreach ($iterationCollection->getRejects() as $reject) {
                $reject->incrementRejectionCount();
                $this->runIteration($executor, $executorConfig, $reject, $context->getSleep($subject->getSleep()));
            }
            $iterationCollection->computeStats();
            $this->logger->iterationsEnd($iterationCollection);
        }

        $stats = $iterationCollection->getStats();

        foreach ($iterationCollection as $iteration) {
            $iterationEl = $variantEl->ownerDocument->createElement('iteration');
            $iterationEl->setAttribute('net-time', $iteration->getResult()->getTime());
            $iterationEl->setAttribute('rev-time', $iteration->getResult()->getTime() / $iteration->getRevolutions());
            $iterationEl->setAttribute('z-value', $iteration->getZValue());
            $iterationEl->setAttribute('memory', $iteration->getResult()->getMemory());
            $iterationEl->setAttribute('deviation', $iteration->getDeviation());
            $iterationEl->setAttribute('rejection-count', $iteration->getRejectionCount());

            $variantEl->appendChild($iterationEl);
        }

        $statsEl = $variantEl->appendElement('stats');
        foreach ($stats as $statName => $statValue) {
            $statsEl->setAttribute($statName, $statValue);
        }
    }

    public function runIteration(ExecutorInterface $executor, Config $executorConfig, Iteration $iteration, $sleep)
    {
        $this->logger->iterationStart($iteration);
        $result = $executor->execute($iteration, $executorConfig);

        if ($sleep) {
            usleep($sleep);
        }

        $iteration->setResult($result);
        $this->logger->iterationEnd($iteration);
    }

    private function appendException(\DOMElement $node, \Exception $exception)
    {
        $errorsEl = $node->appendElement('errors');

        do {
            $errorEl = $errorsEl->appendElement('error', $exception->getMessage());
            $errorEl->setAttribute('exception-class', get_class($exception));
            $errorEl->setAttribute('code', $exception->getCode());
            $errorEl->setAttribute('file', $exception->getFile());
            $errorEl->setAttribute('line', $exception->getLine());
        } while ($exception = $exception->getPrevious());
    }
}
