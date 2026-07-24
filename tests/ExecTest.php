<?php

use PHPUnit\Framework\TestCase;

class ExecTest extends TestCase
{
    public function testArrayCommandSuccess()
    {
        $p = Exec::run(["ls", "/etc"]);
        $this->assertEquals(0, $p->retval);
        $this->assertEquals("", $p->stderr);
        $this->assertNotEmpty($p->stdout);
    }

    public function testArrayCommandInvalidArg()
    {
        $p = Exec::run(["ls", "/etcasdasdasd"]);
        $this->assertEquals(2, $p->retval);
        $this->assertEquals("ls: cannot access '/etcasdasdasd': No such file or directory\n", $p->stderr);
        $this->assertEmpty($p->stdout);
    }


    public function testStringCommandNotFound()
    {
        $p = Exec::run("lsasdasdasdasdasd /etc");
        $this->assertEquals(127, $p->retval);
        $this->assertEquals("sh: 1: lsasdasdasdasdasd: not found\n", $p->stderr);
        $this->assertEmpty($p->stdout);
    }

    public function testStringCommandStdinUsage()
    {
        $p = Exec::run("cat -", "dog");
        $this->assertEquals(0, $p->retval);
        $this->assertEquals("dog", $p->stdout);
        $this->assertEmpty($p->stderr);
    }
}
