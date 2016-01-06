<?php

/*
 * This file is part of the PHPBench package
 *
 * (c) Daniel Leech <daniel@dantleech.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpBench\Progress\Logger;

use PhpBench\Benchmark\Iteration;
use PhpBench\Benchmark\IterationCollection;
use PhpBench\Benchmark\Metadata\BenchmarkMetadata;
use PhpBench\Benchmark\SuiteDocument;
use PhpBench\Util\TimeUnit;
use PhpBench\Math\Statistics;

class HistogramLogger extends PhpBenchLogger
{
    private $blocks = array(' ', '▁',  '▂',  '▃',  '▄',  '▅',  '▆', '▇', '█');

    /**
     * {@inheritdoc}
     */
    public function benchmarkStart(BenchmarkMetadata $benchmark)
    {
        static $first = true;

        if (false === $first) {
            $this->output->write(PHP_EOL);
        }
        $first = false;
        $this->output->write(sprintf('<comment>%s</comment>', $benchmark->getClass()));
        $subjectNames = array();
        foreach ($benchmark->getSubjectMetadatas() as $subject) {
            $subjectNames[] = sprintf('#%s %s', $subject->getIndex(), $subject->getName());
        }

        $this->output->write(sprintf(' (%s)', implode(', ', $subjectNames)));
        $this->output->write(PHP_EOL);
        $this->output->write("\x1B[2K");
        $this->output->write(PHP_EOL);
    }

    /**
     * {@inheritdoc}
     */
    public function iterationsStart(IterationCollection $iterations)
    {
        $this->drawIterations($iterations);
        $this->output->write("\x1B[1A"); // move cursor up
    }

    /**
     * {@inheritdoc}
     */
    public function iterationsEnd(IterationCollection $iterations)
    {
        $this->drawIterations($iterations);

        if ($iterations->hasException()) {
            $this->output->write(' <error>ERROR</error>');
            $this->output->write("\x1B[0J"); // clear the rest of the line
            $this->output->write(PHP_EOL);

            return;
        }

        if ($iterations->getRejectCount() > 0) {
            $this->output->write("\x1B[1A"); // move cursor up
            $this->output->write("\x1B[0G");
            return;
        }
    }

    private function drawIterations(IterationCollection $iterations)
    {
        $subject = $iterations->getSubject();
        $this->output->write("\x1B[2K"); // clear the whole line
        $this->output->write(PHP_EOL);
        $this->output->write("\x1B[2K"); // clear the whole line
        $this->output->write("\x1B[1A");

        $steps = 60;
        $sigma = 2;

        if ($iterations->isComputed()) {
            $times = $iterations->getZValues();
            $stats = $iterations->getStats();
            $freqs = Statistics::histogram($times, $steps, -$sigma, $sigma);
        } else {
            $stats = array('stdev' => 0, 'mean' => 0, 'max' => 0);
            $freqs = array_fill(0, $steps + 1, null);
        }

        $this->output->write(sprintf(
            '#%-2d (σ = %s ) -%sσ [',
            $subject->getIndex(),
            $this->timeUnit->format($stats['stdev']),
            $sigma
        ));
        $this->drawBlocks($freqs, $stats);

        $this->output->write(sprintf(
            '] +%sσ (mean = %s, <fg=blue>peak</> x %d)',
            $sigma,
            $this->timeUnit->format($stats['max']),
            max($freqs)
        ));

        $this->output->write(PHP_EOL);
    }

    private function drawBlocks($freqs)
    {
        $max = max($freqs);
        $middle = floor(count($freqs) / 2);

        $height = 8;
        $index = 1;
        while (false !== $freq = current($freqs)) {
            next($freqs);

            if ($index++ == $middle) {
                $this->output->write("\x1B[1B");
                $this->output->write('T');
                $this->output->write("\x1B[1A");
                $this->output->write("\x1B[1D");
            }

            if (null === $freq) {
                $this->output->write(' ');
                continue;
            }

            $level = $max > 0 ? floor($height / $max * $freq) : 0;

            if ($max == $freq) {
                $this->output->write(sprintf('<fg=blue>%s</>', $this->blocks[$level]));
            } else {
                $this->output->write($this->blocks[$level]);
            }
        }

    }

    /**
     * {@inheritdoc}
     */
    public function iterationStart(Iteration $iteration)
    {
        $this->output->write(PHP_EOL);
        $this->output->write(PHP_EOL);
        $this->output->write(sprintf(
            '<info>it</info>%3d/%-3d<info> (rej </info>%s<info>) subject </info>%s<info> revs </info>%d',
            $iteration->getIndex(),
            $iteration->getCollection()->count(),
            count($iteration->getCollection()->getRejects()),
            sprintf('%s', $iteration->getCollection()->getSubject()->getName()),
            $iteration->getRevolutions()
        ));
        $this->output->write(PHP_EOL);
        $this->output->write(sprintf(
            '<info>parameters</info> %s',
            json_encode($iteration->getCollection()->getParameterSet()->getArrayCopy(), true)
        ));
        $this->output->write("\x1B[3A"); // put the cursor back to the line with the measurements
        $this->output->write("\x1B[0G"); // put the cursor back at col 0
    }
}

