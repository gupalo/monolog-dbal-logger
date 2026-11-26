<?php

namespace Gupalo\MonologDbalLogger\Tests;

use DateTimeImmutable;
use Gupalo\MonologDbalLogger\Entity\Log;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccess;

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

        $this->assertNull($log->getLevelName());
    }

    public function testGetMessage(): void
    {
        $log = new Log();

        $this->assertEquals('', $log->getMessage());
    }

    public function testGetContext(): void
    {
        $log = new Log();

        $this->assertNull($log->getContext());
        $this->assertNull($log->getContextRaw());
    }

    public function testGetChannel(): void
    {
        $log = new Log();

        $this->assertEquals('', $log->getChannel());
    }

    public function testGetCmd(): void
    {
        $log = new Log();

        $this->assertNull($log->getCmd());
    }

    public function testGetMethod(): void
    {
        $log = new Log();

        $this->assertNull($log->getMethod());
    }

    public function testGetUid(): void
    {
        $log = new Log();

        $this->assertNull($log->getUid());
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

        $this->assertNull($log->getExceptionClass());
    }

    public function testGetExceptionMessage(): void
    {
        $log = new Log();

        $this->assertNull($log->getExceptionMessage());
    }

    public function testGetExceptionLine(): void
    {
        $log = new Log();

        $this->assertNull($log->getExceptionLine());
    }

    public function testGetExceptionTrace(): void
    {
        $log = new Log();

        $this->assertNull($log->getExceptionTrace());
    }

    public function testGetContextDecodesJson(): void
    {
        $log = $this->logWithContext('{"a":1,"b":{"c":"d"}}');

        $this->assertSame(['a' => 1, 'b' => ['c' => 'd']], $log->getContext());
        $this->assertSame('{"a":1,"b":{"c":"d"}}', $log->getContextRaw());
    }

    public function testGetContextKeepsTruncatedJsonAsRaw(): void
    {
        $log = $this->logWithContext('{"a":"trunc');

        $this->assertSame(['raw' => '{"a":"trunc'], $log->getContext());
    }

    public function testGetContextKeepsScalarJsonAsRaw(): void
    {
        $log = $this->logWithContext('"just a string"');

        $this->assertSame(['raw' => '"just a string"'], $log->getContext());
    }

    public function testGetContextEmptyIsNull(): void
    {
        $this->assertNull($this->logWithContext('')->getContext());
    }

    public function testContextReadThroughPropertyAccessIsDecoded(): void
    {
        // EasyAdmin (DbalLoggerYamlField) and Symfony forms read "context" via PropertyAccess, i.e. getContext()
        $log = $this->logWithContext('{"a":{"b":1}}');

        $value = PropertyAccess::createPropertyAccessor()->getValue($log, 'context');

        $this->assertSame(['a' => ['b' => 1]], $value);
    }

    private function logWithContext(string $context): Log
    {
        $log = new Log();
        (new \ReflectionProperty(Log::class, 'context'))->setValue($log, $context);

        return $log;
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
