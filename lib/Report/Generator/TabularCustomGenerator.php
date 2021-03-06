<?php

/*
 * This file is part of the PHPBench package
 *
 * (c) Daniel Leech <daniel@dantleech.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpBench\Report\Generator;

use PhpBench\Benchmark\SuiteDocument;
use PhpBench\Registry\Config;
use PhpBench\Tabular\Definition\Loader;
use PhpBench\Tabular\Tabular;

/**
 * Report generator which uses custom report definitions.
 */
class TabularCustomGenerator extends AbstractTabularGenerator
{
    private $configPath;

    public function __construct(Tabular $tabular, Loader $loader, $configPath)
    {
        parent::__construct($tabular, $loader);
        $this->configPath = $configPath;
    }

    /**
     * {@inheritdoc}
     */
    public function getSchema()
    {
        return array(
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => array(
                'title' => array(
                    'oneOf' => array(
                        array('type' => 'string'),
                        array('type' => 'null'),
                    ),
                ),
                'description' => array(
                    'oneOf' => array(
                        array('type' => 'string'),
                        array('type' => 'null'),
                    ),
                ),
                'file' => array(
                    'oneOf' => array(
                        array('type' => 'string'),
                        array('type' => 'null'),
                    ),
                ),
                'params' => array(
                    'oneOf' => array(
                        array('type' => 'object'),
                        array('type' => 'array'),
                    ),
                ),
                'debug' => array(
                    'type' => 'boolean',
                ),
                'exclude' => array(
                    'type' => 'array',
                ),
                'formatting' => array(
                    'type' => 'boolean',
                ),
            ),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function generate(SuiteDocument $document, Config $config)
    {
        if (!$config['file']) {
            throw new \InvalidArgumentException(
                'You must provide the path to a Tabular JSON report definition with the "file" option.'
            );
        }

        $reportFile = $config['file'];

        // if not an absolute path, make it relative to the config file
        if (substr($reportFile, 0, 1) !== '/') {
            $reportFile = dirname($this->configPath) . '/' . $reportFile;
        }

        $parameters = $config['params'];

        return $this->doGenerate($reportFile, $document, $config, $parameters);
    }

    /***
     * {@inheritDoc}
     */

    public function getDefaultConfig()
    {
        return array(
            'debug' => false,
            'title' => null,
            'description' => null,
            'file' => null,
            'params' => array(),
            'exclude' => array(),
        );
    }
}
