<?php

namespace PhpBench\Vcs\Detector;

use PhpBench\Vcs\DetectorInterface;
use PhpBench\Vcs\VcsInformation;

class AggregateDetector implements DetectorInterface
{
    private $detectors = array();
    /**
     * {@inheritdoc}
     */
    public function detect()
    {
        throw new \BadMethodCallException('Not implemented');
    }

    /**
     * {@inheritdoc}
     */
    public function getVcsInformation()
    {
        foreach ($this->detectors as $detector) {
            if ($detector->detect()) {
                return $detector->getVcsInformation();
            }
        }

        return new VcsInformation('none');
    }

    /**
     * @param DetectorInterface
     */
    public function addDetector(DetectorInterface $detector)
    {
        $this->detectors[] = $detector;
    }
}
