<?php

namespace PhpBench\Vcs\Detector;

class AggregateDetector implements DetectorInterface
{
    private $detectors = array();

    /**
     * {@inheritdoc}
     */
    public function detect()
    {
        foreach ($this->detectors as $detector) {
            if ($detector->detect()) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getVcsInformation()
    {
        foreach ($this->detectors as $detector) {
            if ($detector->detect) {
                return $detector->getVcsInformation();
            }
        }

        throw new \RuntimeException(sprintf(
            'Could not detect a VCS repository in the current directory ("%s").',
            getcwd()
        ));
    }
}
