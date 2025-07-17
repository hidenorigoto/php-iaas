<?php

declare(strict_types=1);

namespace VmManagement\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VmManagement\Test;

class TestTest extends TestCase
{
    /**

     * @covers \VmManagement\SimpleVM

     */

    public function testHello(): void
    {
        $test = new Test();
        $this->assertEquals('Hello, VM Management!', $test->hello());
    }
}
