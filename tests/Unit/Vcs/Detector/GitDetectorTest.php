<?php

namespace PhpBench\Tests\Unit\Vcs\Detector;

use PhpBench\Vcs\Detector\GitDetector;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class GitDetectorTest extends \PHPUnit_Framework_TestCase
{
    private $detector;
    private $filesystem;
    private $testRepoDir;

    public function setUp()
    {
        $this->filesystem = new Filesystem();

        $this->testRepoDir = __DIR__ . '/testRepo';
        $this->clean();
        $this->filesystem->mkdir($this->testRepoDir);
        chdir($this->testRepoDir);
        file_put_contents(sprintf('%s/foobar', $this->testRepoDir), 'Foobar');
        $this->exec('git init');
        $this->exec('git add foobar');

        $this->detector = new GitDetector();
    }

    public function tearDown()
    {
        $this->clean();
    }

    /**
     * It should return TRUE if the CWD is a git repository.
     */
    public function testDetect()
    {
        $result = $this->detector->detect();
        $this->assertTrue($result);
    }

    /**
     * It should return the VCS information for the current git repository.
     */
    public function testGetVcsInformation()
    {
        $info = $this->detector->getVcsInformation();
        $this->assertEquals('git', $info->getSystem());
        $this->assertEquals('master', $info->getBranch());
        $this->assertNull($info->getVersion()); // no commit has yet been made
    }

    /**
     * It should show the commitsh
     */
    public function testGetVcsCommitsh()
    {
        $this->exec('git commit -m "test"');
        $info = $this->detector->getVcsInformation();
        $this->assertNotNull($info->getVersion()); // no commit has yet been made
        $this->assertEquals(40, strlen($info->getVersion()));
    }

    /**
     * It should show the branch
     */
    public function testGetVcsBranch()
    {
        $this->exec('git commit -m "test"');

        $this->exec('git branch foobar');
        $this->exec('git checkout foobar');
        $info = $this->detector->getVcsInformation();
        $this->assertEquals('foobar', $info->getBranch());
    }

    private function clean()
    {
        if (file_exists($this->testRepoDir)) {
            $this->filesystem->remove(__DIR__ . '/testRepo');
        }
    }

    private function exec($cmd)
    {
        $proc = new Process($cmd);
        $exitCode = $proc->run();

        if ($exitCode !== 0) {
            throw new \RuntimeException(sprintf(
                'Could not execute command: %s',
                $proc->getErrorOutput()
            ));
        }
    }
}
