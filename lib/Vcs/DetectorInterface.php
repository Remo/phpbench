<?php

namespace PhpBench\Vcs;

interface DetectorInterface
{
    /**
     * Return true if the instance detects a VCS repository
     * in the current CWD.
     *
     * @return boolean
     */
    public function detect();

    /**
     * Return information about the detected VCS repository.
     *
     * @return VcsInformation
     * @throws RuntimeException
     */
    public function getVcsInformation();
}
