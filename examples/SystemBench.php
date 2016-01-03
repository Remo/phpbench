<?php

/**
 * @Iterations(10)
 * @Revs(1000)
 * @OutputMode("throughput")
 * @OutputTimeUnit("seconds")
 */
class SystemBench
{
    private $handle;
    private $tmpName;

    public function openFile()
    {
        $this->tmpName = tempnam(sys_get_temp_dir(), 'phpbench_system');
        $this->handle = fopen($this->tmpName, 'w');
    }

    public function removeFile()
    {
        fclose($this->handle);
        unlink($this->tmpName);
    }

    public function benchMd5()
    {
        md5('hello');
    }

    public function benchNothing()
    {
    }

    /**
     * @BeforeMethods({"openFile"})
     * @AfterMethods({"removeFile"})
     * @Revs(1)
     */
    public function benchWriteOneMegabyte()
    {
        $file = '';
        
        $megabyte = 1024 ^2;
        $file  = str_repeat(chr(0x63), $megabyte);

        fwrite($this->handle, $file);
    }

    /**
     * @BeforeMethods({"openFile", "benchWriteOneMegabyte"})
     * @AfterMethods({"removeFile"})
     * @Revs(1)
     */
    public function benchReadOneMegabyte()
    {
        fread($this->handle, filesize($this->tmpName));
    }
}
