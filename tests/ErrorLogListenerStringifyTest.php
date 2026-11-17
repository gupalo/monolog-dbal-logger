<?php

namespace Gupalo\MonologDbalLogger\Tests;

use Gupalo\MonologDbalLogger\Symfony\ErrorLogListener;
use PHPUnit\Framework\TestCase;

class ErrorLogListenerStringifyTest extends TestCase
{
    public function testStringifyMasksEmailAddresses(): void
    {
        $reflection = new \ReflectionClass(ErrorLogListener::class);
        $method = $reflection->getMethod('stringify');

        $result = $method->invoke(null, 'Contact user@example.com for help');

        $this->assertStringContainsString('***@example.com', $result);
        $this->assertStringNotContainsString('user@example.com', $result);
    }

    public function testStringifyMasksPasswordsInUrls(): void
    {
        $reflection = new \ReflectionClass(ErrorLogListener::class);
        $method = $reflection->getMethod('stringify');

        $result = $method->invoke(null, 'mysql://user:secret@localhost/db');

        $this->assertStringContainsString('://user:***@', $result);
        $this->assertStringNotContainsString('secret', $result);
    }

    public function testStringifyTruncatesLongStrings(): void
    {
        $reflection = new \ReflectionClass(ErrorLogListener::class);
        $method = $reflection->getMethod('stringify');

        $longString = str_repeat('a', 20000);
        $result = $method->invoke(null, $longString);

        $this->assertEquals(10000, strlen($result));
    }

    public function testStringifyEncodesArrays(): void
    {
        $reflection = new \ReflectionClass(ErrorLogListener::class);
        $method = $reflection->getMethod('stringify');

        $result = $method->invoke(null, ['key' => 'value']);

        $this->assertEquals('{"key":"value"}', $result);
    }

    public function testStringifyKeepsArraysWithInvalidUtf8(): void
    {
        $method = (new \ReflectionClass(ErrorLogListener::class))->getMethod('stringify');

        $result = $method->invoke(null, ['q' => "bad\xB1\x31byte"]);

        $this->assertStringContainsString('"q":"bad', $result);
    }

    public function testStringifyDoesNotCastTooDeepArraysToString(): void
    {
        $method = (new \ReflectionClass(ErrorLogListener::class))->getMethod('stringify');
        $deep = 'leaf';
        for ($i = 0; $i < 12; ++$i) {
            $deep = ['a' => $deep];
        }

        set_error_handler(static fn (int $no, string $msg): never => throw new \ErrorException($msg, 0, $no));
        try {
            $result = $method->invoke(null, $deep);
        } finally {
            restore_error_handler();
        }

        $this->assertStringStartsWith('[unencodable array:', $result);
    }

    public function testStringifyMasksUrlCredentialsInArraysWithoutCrossingValues(): void
    {
        $method = (new \ReflectionClass(ErrorLogListener::class))->getMethod('stringify');

        $result = $method->invoke(null, [
            'values' => ['http://example.org:8080/path', 'alice@example.com'],
            'dsn' => 'mysql://user:secret@localhost/db',
            'quoted' => 'redis://u:se"cr\\et@host:6379',
            'user@example.org' => 'key holds an e-mail',
        ]);

        $this->assertStringContainsString('"http://example.org:8080/path"', $result);
        $this->assertStringContainsString('"***@example.com"', $result);
        $this->assertStringContainsString('mysql://user:***@localhost/db', $result);
        $this->assertStringNotContainsString('secret', $result);
        $this->assertStringContainsString('redis://u:***@host:6379', $result);
        $this->assertStringNotContainsString('cr', $result);
        $this->assertStringContainsString('"***@example.org"', $result);
    }

    public function testStringifyDoesNotLetJsonSerializeExceptionsEscape(): void
    {
        $method = (new \ReflectionClass(ErrorLogListener::class))->getMethod('stringify');
        $bad = new class implements \JsonSerializable {
            public function jsonSerialize(): mixed
            {
                throw new \RuntimeException('boom');
            }
        };

        $result = $method->invoke(null, ['x' => $bad]);

        $this->assertSame('[unencodable array: boom]', $result);
    }

    public function testStringifyBoundsTheWorkOnLargeArrayValues(): void
    {
        $method = (new \ReflectionClass(ErrorLogListener::class))->getMethod('stringify');
        $data = ['a' => str_repeat('x', 3_000_000), 'b' => str_repeat('y', 3_000_000), 'c' => 'user@example.com'];

        $result = $method->invoke(null, $data);

        $this->assertLessThanOrEqual(10000, strlen($result));
        $this->assertStringNotContainsString('y', $result);
        $this->assertStringNotContainsString('user@example.com', $result);
    }

    public function testStringifyNeverReturnsUnmaskedValuesWhenPcreFails(): void
    {
        $method = (new \ReflectionClass(ErrorLogListener::class))->getMethod('stringify');
        $limit = ini_get('pcre.backtrack_limit');
        ini_set('pcre.backtrack_limit', '1');
        try {
            $result = $method->invoke(null, ['dsn' => 'mysql://user:secret@localhost/db '.str_repeat('a', 5000)]);
        } finally {
            ini_set('pcre.backtrack_limit', (string) $limit);
        }

        $this->assertStringNotContainsString('secret', $result);
    }

    public function testStringifyMasksKeysAndJsonSerializableOutput(): void
    {
        $method = (new \ReflectionClass(ErrorLogListener::class))->getMethod('stringify');
        $object = new class implements \JsonSerializable {
            public function jsonSerialize(): mixed
            {
                return ['dsn' => 'mysql://u:objsecret@host/db', 'mail' => 'bob@example.com'];
            }
        };

        $result = $method->invoke(null, ['mysql://user:keysecret@host' => '1', 'obj' => $object]);

        $this->assertStringNotContainsString('keysecret', $result);
        $this->assertStringNotContainsString('objsecret', $result);
        $this->assertStringNotContainsString('bob@', $result);
        $this->assertStringContainsString('mysql://user:***@host', $result);
        $this->assertStringContainsString('mysql://u:***@host/db', $result);
    }
}
