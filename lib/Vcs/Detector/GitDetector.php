<?php

namespace PhpBench\Vcs\Detector;

use PhpBench\Vcs\DetectorInterface;
use Symfony\Component\Process\Process;
use PhpBench\Vcs\VcsInformation;

class GitDetector implements DetectorInterface
{
    const SYSTEM = 'git';

    /**
     * {@inheritdoc}
     */
    public function detect()
    {
        $index = sprintf('%s/.git', getcwd());
        if (file_exists($index)) {
            return true;
        }

        return false;
    }

    public function getVcsInformation()
    {
        $cmd = 'git symbolic-ref HEAD';
        $process = $this->exec($cmd);
        preg_match('{^refs/heads/(.*)$}', $process->getOutput(), $matches);
        $branchName = $matches[1] ?: '(unamed branch)';
        $commitshRef = sprintf(
            '%s/%s/%s',
            getcwd(),
            '.git/refs/heads',
            $branchName
        );

        if (!file_exists($commitshRef)) {
            $version = null;
        } else {
            $version = trim(file_get_contents($commitshRef));
        }

        return new VcsInformation(self::SYSTEM, $version, $branchName);
    }

    private function exec($cmd)
    {
        $process = new Process($cmd);
        $exitCode = $process->run();

        if ($exitCode !== 0) {
            throw new \RuntimeException(sprintf(
                'Error encountered running "%s" command: ',
                $cmd, $process->getErrorOutput()
            ));
        }

        return $process;
    }
}
