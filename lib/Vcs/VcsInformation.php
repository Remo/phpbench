<?php

namespace PhpBench\Vcs;

class VcsInformation
{
    private $detectors = array();

    public function detect()
    {
        foreach ($this->detectors as $detector) {
            if (false === $detector->detect()) {
                continue;
            }

            return $detector->getVcsInformation();
        }
    }
}
