<?php

namespace PhpBench\Vcs\Detector;

class GitDetector implements DetectorInterface
{
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
}
