<?php

namespace Gupalo\MonologDbalLogger\Tests;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use ErrorException;
use Exception;
use Gupalo\MonologDbalLogger\MonologDbalCleaner;
use Gupalo\MonologDbalLogger\MyMonologDbalLogger;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

class MyMonologDbalLoggerTest extends TestCase
{
    public function testHandleWithAdditionalFields(): void
    {
        $connection = $this->createMock(Connection::class);
        $cleaner = $this->createMock(MonologDbalCleaner::class);

        $connection->expects($this->once())
            ->method('insert')
            ->with('_log', $this->callback(function (array $data) {
                return $data['cmd'] === 'app:test'
                    && $data['method'] === 'POST'
                    && $data['uid'] === 'abc123'
                    && $data['count'] === 42
                    && $data['time'] === 1.5;
            }));

        $logger = new MyMonologDbalLogger($connection, '_log', 100000, Level::Debug, true, $cleaner);

        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Test',
            context: [
                'cmd' => 'app:test',
                'method' => 'POST',
                'uid' => 'abc123',
                'count' => 42,
                'time' => 1.5,
            ],
        );

        $logger->handle($record);
    }

    public function testHandleWithException(): void
    {
        $connection = $this->createMock(Connection::class);
        $cleaner = $this->createMock(MonologDbalCleaner::class);

        $exception = new Exception('Test exception message');

        $connection->expects($this->once())
            ->method('insert')
            ->with('_log', $this->callback(function (array $data) {
                return $data['exception_class'] === Exception::class
                    && $data['exception_message'] === 'Test exception message'
                    && str_contains($data['exception_line'], '.php:')
                    && !empty($data['exception_trace']);
            }));

        $logger = new MyMonologDbalLogger($connection, '_log', 100000, Level::Debug, true, $cleaner);

        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Error,
            message: 'Error occurred',
            context: ['exception' => $exception],
        );

        $logger->handle($record);
    }

    public function testHandleSkipsDeprecatedMessage(): void
    {
        $connection = $this->createMock(Connection::class);
        $cleaner = $this->createMock(MonologDbalCleaner::class);

        $connection->expects($this->never())->method('insert');

        $logger = new MyMonologDbalLogger($connection, '_log', 100000, Level::Debug, true, $cleaner);

        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Warning,
            message: 'User Deprecated: The "Doctrine\\Common\\Inflector\\Inflector::classify" method is deprecated and will be dropped in doctrine/inflector 2.0. Please update to the new Inflector API.',
        );

        $logger->handle($record);
    }

    public function testHandleSkipsUserDeprecatedException(): void
    {
        $connection = $this->createMock(Connection::class);
        $cleaner = $this->createMock(MonologDbalCleaner::class);

        $connection->expects($this->never())->method('insert');

        $logger = new MyMonologDbalLogger($connection, '_log', 100000, Level::Debug, true, $cleaner);

        $exception = new ErrorException('Deprecated', 0, E_USER_DEPRECATED);

        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Warning,
            message: 'Deprecated warning',
            context: ['exception' => $exception],
        );

        $logger->handle($record);
    }

    public function testHandleDoesNotSkipRegularErrorException(): void
    {
        $connection = $this->createMock(Connection::class);
        $cleaner = $this->createMock(MonologDbalCleaner::class);

        $connection->expects($this->once())->method('insert');

        $logger = new MyMonologDbalLogger($connection, '_log', 100000, Level::Debug, true, $cleaner);

        $exception = new ErrorException('Error', 0, E_ERROR);

        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Error,
            message: 'Error occurred',
            context: ['exception' => $exception],
        );

        $logger->handle($record);
    }

    public function testHandleWithCmdInContext(): void
    {
        $connection = $this->createMock(Connection::class);
        $cleaner = $this->createMock(MonologDbalCleaner::class);

        $connection->expects($this->once())
            ->method('insert')
            ->with('_log', $this->callback(function (array $data) {
                return $data['cmd'] === 'app:command';
            }));

        $logger = new MyMonologDbalLogger($connection, '_log', 100000, Level::Debug, true, $cleaner);

        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Test',
            context: ['cmd' => 'app:command'],
        );

        $logger->handle($record);
    }

    public function testHandleWithExceptionFieldsInContext(): void
    {
        $connection = $this->createMock(Connection::class);
        $cleaner = $this->createMock(MonologDbalCleaner::class);

        $connection->expects($this->once())
            ->method('insert')
            ->with('_log', $this->callback(function (array $data) {
                return $data['exception_class'] === 'CustomException'
                    && $data['exception_message'] === 'Custom message';
            }));

        $logger = new MyMonologDbalLogger($connection, '_log', 100000, Level::Debug, true, $cleaner);

        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Error,
            message: 'Error',
            context: [
                'exception_class' => 'CustomException',
                'exception_message' => 'Custom message',
            ],
        );

        $logger->handle($record);
    }

    public function testHandleRemovesAdditionalFieldsFromContext(): void
    {
        $connection = $this->createMock(Connection::class);
        $cleaner = $this->createMock(MonologDbalCleaner::class);

        $connection->expects($this->once())
            ->method('insert')
            ->with('_log', $this->callback(function (array $data) {
                $context = json_decode($data['context'], true);
                // cmd, method, uid should be removed from context
                return !isset($context['cmd'])
                    && !isset($context['method'])
                    && !isset($context['uid'])
                    && isset($context['other_field']);
            }));

        $logger = new MyMonologDbalLogger($connection, '_log', 100000, Level::Debug, true, $cleaner);

        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Test',
            context: [
                'cmd' => 'test',
                'method' => 'GET',
                'uid' => '123',
                'other_field' => 'value',
            ],
        );

        $logger->handle($record);
    }
}
