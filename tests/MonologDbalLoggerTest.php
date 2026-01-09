<?php

namespace Gupalo\MonologDbalLogger\Tests;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Gupalo\MonologDbalLogger\MonologDbalCleaner;
use Gupalo\MonologDbalLogger\MonologDbalLogger;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

class MonologDbalLoggerTest extends TestCase
{
    public function testHandle(): void
    {
        $connection = $this->createMock(Connection::class);
        $cleaner = $this->createMock(MonologDbalCleaner::class);

        $connection->expects($this->once())
            ->method('insert')
            ->with('_log', $this->callback(function (array $data) {
                return $data['level'] === Level::Warning->value
                    && $data['level_name'] === 'warning'
                    && $data['channel'] === 'test'
                    && $data['message'] === 'Test message'
                    && $data['context'] === '{"key":"value"}';
            }));

        $cleaner->expects($this->once())->method('cleanup');

        $logger = new MonologDbalLogger($connection, '_log', 100000, Level::Debug, true, $cleaner);

        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Warning,
            message: 'Test message',
            context: ['key' => 'value'],
        );

        $logger->handle($record);
    }

    public function testHandleWithCustomTable(): void
    {
        $connection = $this->createMock(Connection::class);
        $cleaner = $this->createMock(MonologDbalCleaner::class);

        $connection->expects($this->once())
            ->method('insert')
            ->with('custom_log', $this->anything());

        $logger = new MonologDbalLogger($connection, 'custom_log', 100000, Level::Debug, true, $cleaner);

        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Test',
        );

        $logger->handle($record);
    }

    public function testHandleBelowLevel(): void
    {
        $connection = $this->createMock(Connection::class);
        $cleaner = $this->createMock(MonologDbalCleaner::class);

        $connection->expects($this->never())->method('insert');
        $cleaner->expects($this->never())->method('cleanup');

        $logger = new MonologDbalLogger($connection, '_log', 100000, Level::Warning, true, $cleaner);

        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Debug,
            message: 'Debug message',
        );

        $logger->handle($record);
    }

    public function testHandleEmptyContext(): void
    {
        $connection = $this->createMock(Connection::class);
        $cleaner = $this->createMock(MonologDbalCleaner::class);

        $connection->expects($this->once())
            ->method('insert')
            ->with('_log', $this->callback(function (array $data) {
                return $data['context'] === null;
            }));

        $logger = new MonologDbalLogger($connection, '_log', 100000, Level::Debug, true, $cleaner);

        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Test',
            context: [],
        );

        $logger->handle($record);
    }

    public function testHandleLongMessage(): void
    {
        $connection = $this->createMock(Connection::class);
        $cleaner = $this->createMock(MonologDbalCleaner::class);

        $longMessage = str_repeat('a', 2000);

        $connection->expects($this->once())
            ->method('insert')
            ->with('_log', $this->callback(function (array $data) {
                return strlen($data['message']) === 1024;
            }));

        $logger = new MonologDbalLogger($connection, '_log', 100000, Level::Debug, true, $cleaner);

        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: $longMessage,
        );

        $logger->handle($record);
    }

    public function testHandleNormalizesLevelName(): void
    {
        $connection = $this->createMock(Connection::class);
        $cleaner = $this->createMock(MonologDbalCleaner::class);

        $connection->expects($this->once())
            ->method('insert')
            ->with('_log', $this->callback(function (array $data) {
                return $data['level_name'] === 'error';
            }));

        $logger = new MonologDbalLogger($connection, '_log', 100000, Level::Debug, true, $cleaner);

        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Error,
            message: 'Error message',
        );

        $logger->handle($record);
    }

    public function testHandleWithExtraData(): void
    {
        $connection = $this->createMock(Connection::class);
        $cleaner = $this->createMock(MonologDbalCleaner::class);

        $connection->expects($this->once())
            ->method('insert')
            ->with('_log', $this->callback(function (array $data) {
                $context = json_decode($data['context'], true);
                return isset($context['context_key']) && isset($context['extra_key']);
            }));

        $logger = new MonologDbalLogger($connection, '_log', 100000, Level::Debug, true, $cleaner);

        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Test',
            context: ['context_key' => 'context_value'],
            extra: ['extra_key' => 'extra_value'],
        );

        $logger->handle($record);
    }

    public function testHandleSilentlyFailsOnInsertException(): void
    {
        $connection = $this->createMock(Connection::class);
        $cleaner = $this->createMock(MonologDbalCleaner::class);

        $connection->expects($this->once())
            ->method('insert')
            ->willThrowException(new \Exception('Database error'));

        $logger = new MonologDbalLogger($connection, '_log', 100000, Level::Debug, true, $cleaner);

        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Test',
        );

        // Should not throw
        $logger->handle($record);
        $this->addToAssertionCount(1);
    }
}
