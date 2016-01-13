<?php

namespace PhpBench\Tests\Unit\Vcs\Detector;

use PhpBench\Vcs\Detector\AggregateDetector;

class AggregateDetectorTest extends \PHPUnit_Framework_TestCase
{
    private $detector;
    private $detector1;
    private $detector2;
    private $vcsInformation;

    public function setUp()
    {
        $this->detector = new AggregateDetector();

        $this->detector1 = $this->prophesize('PhpBench\Vcs\DetectorInterface');
        $this->detector2 = $this->prophesize('PhpBench\Vcs\DetectorInterface');

        $this->detector->addDetector($this->detector1->reveal());
        $this->detector->addDetector($this->detector2->reveal());
        $this->vcsInformation = $this->prophesize('PhpBench\Vcs\VcsInformation');
    }

    /**
     * It should get the VCS Information for the detector that supports the current
     * VCS system.
     */
    public function testGetVcsInformation()
    {
        $this->detector1->detect()->willReturn(false);
        $this->detector2->detect()->willReturn(true);
        $this->detector2->getVcsInformation()->willReturn($this->vcsInformation->reveal());

        $info = $this->detector->getVcsInformation();

        $this->assertSame($this->vcsInformation->reveal(), $info);
    }

    /**
     * If no VCS detected it should return a VcsInformation with sytsem = none
     */
    public function testGetVcsInformationNoSupport()
    {
        $this->detector1->detect()->willReturn(false);
        $this->detector2->detect()->willReturn(false);

        $info = $this->detector->getVcsInformation();
        $this->assertInstanceOf('PhpBench\Vcs\VcsInformation', $info);
        $this->assertEquals('none', $info->getSystem());
    }
}
