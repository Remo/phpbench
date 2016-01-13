<?php

namespace PhpBench\Vcs;

/**
 * Represents information about the VCS system used by the current working
 * directory.
 */
class VcsInformation
{
    private $system;
    private $version;
    private $branch;

    /**
     * __construct
     *
     * @param string $system
     * @param stirng $version
     * @param string $branch
     */
    public function __construct($system, $version = null, $branch = null)
    {
        $this->system = $system;
        $this->version = $version;
        $this->branch = $branch;
    }

    /**
     * Return the current VCS version.
     *
     * e.g. the commit hash for a git repository.
     *
     * @return string
     */
    public function getVersion() 
    {
        return $this->version;
    }

    /**
     * Return the VCS system, e.g. git, svn, mecurial, etc.
     *
     * @return string
     */
    public function getSystem() 
    {
        return $this->system;
    }

    /**
     * Return the current branch.
     *
     * @return string
     */
    public function getBranch() 
    {
        return $this->branch;
    }
}
