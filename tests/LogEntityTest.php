<?php

namespace Gupalo\MonologDbalLogger\Tests;

use DateTimeImmutable;
use Gupalo\MonologDbalLogger\Entity\Log;
use PHPUnit\Framework\TestCase;

class LogEntityTest extends TestCase
{
    public function testGetId(): void
    {
        $log = new Log();

        $this->assertNull($log->getId());
    }

    public function testGetCreatedAt(): void
    {
        $log = new Log();

        $this->assertNull($log->getCreatedAt());
    }

    public function testGetLevelName(): void
    {
        $log = new Log();

        $this->assertEquals('', $log->getLevelName());
    }

    public function testGetMessage(): void
    {
        $log = new Log();

        $this->assertEquals('', $log->getMessage());
    }

    public function testGetContext(): void
    {
        $log = new Log();

        $this->assertEquals([], $log->getContext());
    }

    public function testGetChannel(): void
    {
        $log = new Log();

        $this->assertEquals('', $log->getChannel());
    }

    public function testGetCmd(): void
    {
        $log = new Log();

        $this->assertEquals('', $log->getCmd());
    }

    public function testGetMethod(): void
    {
        $log = new Log();

        $this->assertEquals('', $log->getMethod());
    }

    public function testGetUid(): void
    {
        $log = new Log();

        $this->assertEquals('', $log->getUid());
    }

    public function testGetCount(): void
    {
        $log = new Log();

        $this->assertNull($log->getCount());
    }

    public function testGetTime(): void
    {
        $log = new Log();

        $this->assertNull($log->getTime());
    }

    public function testGetLevel(): void
    {
        $log = new Log();

        $this->assertEquals(0, $log->getLevel());
    }

    public function testGetExceptionClass(): void
    {
        $log = new Log();

        $this->assertEquals('', $log->getExceptionClass());
    }

    public function testGetExceptionMessage(): void
    {
        $log = new Log();

        $this->assertEquals('', $log->getExceptionMessage());
    }

    public function testGetExceptionLine(): void
    {
        $log = new Log();

        $this->assertEquals('', $log->getExceptionLine());
    }

    public function testGetExceptionTrace(): void
    {
        $log = new Log();

        $this->assertEquals('', $log->getExceptionTrace());
    }

    public function testEntityCanBeInstantiated(): void
    {
        $log = new Log();

        $this->assertInstanceOf(Log::class, $log);
    }

    // Tests for DbalLoggerVirtualFieldsEntityTrait

    public function testVirtual(): void
    {
        $log = new Log();

        $this->assertEquals('', $log->virtual());
    }

    public function testVirtualString(): void
    {
        $log = new Log();

        $this->assertEquals('', $log->virtualString());
    }

    public function testVirtualInt(): void
    {
        $log = new Log();

        $this->assertEquals(0, $log->virtualInt());
    }

    public function testVirtualFloat(): void
    {
        $log = new Log();

        $this->assertEquals(0.0, $log->virtualFloat());
    }

    public function testVirtualBool(): void
    {
        $log = new Log();

        $this->assertFalse($log->virtualBool());
    }

    public function testVirtualStringNull(): void
    {
        $log = new Log();

        $this->assertNull($log->virtualStringNull());
    }

    public function testVirtualIntNull(): void
    {
        $log = new Log();

        $this->assertNull($log->virtualIntNull());
    }

    public function testVirtualFloatNull(): void
    {
        $log = new Log();

        $this->assertNull($log->virtualFloatNull());
    }

    public function testVirtualBoolNull(): void
    {
        $log = new Log();

        $this->assertNull($log->virtualBoolNull());
    }
}
