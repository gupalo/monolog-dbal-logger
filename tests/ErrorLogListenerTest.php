<?php

namespace Gupalo\MonologDbalLogger\Tests;

use Exception;
use Gupalo\MonologDbalLogger\Symfony\ErrorLogListener;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ErrorLogListenerTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset static state between tests
        $reflection = new \ReflectionClass(ErrorLogListener::class);

        $activeCommandName = $reflection->getProperty('activeCommandName');
        $activeCommandName->setValue(null, null);

        $timeCommandBegin = $reflection->getProperty('timeCommandBegin');
        $timeCommandBegin->setValue(null, null);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = ErrorLogListener::getSubscribedEvents();

        $this->assertArrayHasKey('kernel.request', $events);
        $this->assertArrayHasKey('kernel.exception', $events);
        $this->assertArrayHasKey('console.command', $events);
        $this->assertArrayHasKey('console.error', $events);
        $this->assertArrayHasKey('console.terminate', $events);
    }

    public function testOnKernelException(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('log')
            ->with(LogLevel::ERROR, 'exception', $this->callback(function (array $context) {
                return $context['exception_class'] === Exception::class
                    && $context['exception_message'] === 'Test error'
                    && $context['ip'] === '127.0.0.1'
                    && $context['url'] === 'http://localhost/test';
            }));

        $listener = new ErrorLogListener($logger);

        $request = Request::create('http://localhost/test', 'GET');
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, new Exception('Test error'));

        $listener->onKernelException($event);
    }

    public function testOnKernelExceptionFiltersRequestParamsBeforeStringifying(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('log')
            ->with(LogLevel::ERROR, 'exception', $this->callback(function (array $context) {
                return $context['query'] === '{"token":"[hidden]","page":"2"}'
                    && $context['request'] === '{"password":"[hidden]","name":"x"}'
                    // the raw token would be back in the query string of the URL
                    && $context['url'] === 'http://localhost/login';
            }));

        $listener = new class($logger) extends ErrorLogListener {
            protected function filterRequestParams(array $params): array
            {
                return array_replace($params, array_intersect_key(['token' => '[hidden]', 'password' => '[hidden]'], $params));
            }
        };

        $request = Request::create('http://localhost/login?token=t&page=2', 'POST', ['password' => 'hunter2', 'name' => 'x']);
        $kernel = $this->createStub(HttpKernelInterface::class);

        $listener->onKernelException(new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, new Exception('boom')));
    }

    public function testOnKernelExceptionKeepsTheUrlWhenTheFilterLeavesTheQueryAsItIs(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('log')
            ->with(LogLevel::ERROR, 'exception', $this->callback(function (array $context) {
                return $context['url'] === 'http://localhost/search?page=2&q=x'
                    && $context['query'] === '{"page":"2","q":"x"}';
            }));

        $listener = new class($logger) extends ErrorLogListener {
            protected function filterRequestParams(array $params): array
            {
                unset($params['password']);

                return $params;
            }
        };

        $request = Request::create('http://localhost/search?page=2&q=x', 'POST', ['password' => 'hunter2']);
        $kernel = $this->createStub(HttpKernelInterface::class);

        $listener->onKernelException(new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, new Exception('boom')));
    }

    public function testOnKernelExceptionDoesNotLogParamsWhenTheFilterFails(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('log')
            ->with(LogLevel::ERROR, 'exception', $this->callback(function (array $context) {
                return $context['query'] === '[filter failed]'
                    && $context['request'] === '[filter failed]'
                    && $context['url'] === 'http://localhost/login';
            }));

        $listener = new class($logger) extends ErrorLogListener {
            protected function filterRequestParams(array $params): array
            {
                throw new \RuntimeException('broken filter');
            }
        };

        $request = Request::create('http://localhost/login?token=t', 'POST', ['password' => 'hunter2']);
        $kernel = $this->createStub(HttpKernelInterface::class);

        $listener->onKernelException(new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, new Exception('boom')));
    }

    /** @return iterable<string, array{\Throwable, string}> */
    public static function kernelExceptionLevelProvider(): iterable
    {
        yield 'generic exception' => [new Exception('boom'), LogLevel::ERROR];
        yield '500 http exception' => [new HttpException(500, 'boom'), LogLevel::ERROR];
        yield '503 http exception' => [new HttpException(503, 'down'), LogLevel::ERROR];
        yield '404 not found' => [new NotFoundHttpException('No route found for "GET /wp-admin/install.php"'), LogLevel::WARNING];
        yield '405 method not allowed' => [new MethodNotAllowedHttpException(['GET']), LogLevel::WARNING];
        yield '429 too many requests' => [new TooManyRequestsHttpException(), LogLevel::WARNING];
        yield '400 bad request' => [new HttpException(400, 'bad'), LogLevel::WARNING];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('kernelExceptionLevelProvider')]
    public function testOnKernelExceptionLogsClientErrorsBelowError(\Throwable $exception, string $expectedLevel): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('log')
            ->with($expectedLevel, 'exception', $this->callback(function (array $context) use ($exception) {
                return $context['exception_class'] === get_class($exception);
            }));

        $listener = new ErrorLogListener($logger);
        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new ExceptionEvent($kernel, Request::create('http://localhost/x'), HttpKernelInterface::MAIN_REQUEST, $exception);

        $listener->onKernelException($event);
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testOnConsoleCommand(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with('console.begin', $this->callback(function (array $context) {
                return $context['cmd'] === 'app:test';
            }));

        $listener = new ErrorLogListener($logger);

        $command = $this->createStub(Command::class);
        $command->method('getName')->willReturn('app:test');

        $input = $this->createStub(InputInterface::class);
        $input->method('getOptions')->willReturn([]);
        $input->method('getArguments')->willReturn(['command' => 'app:test']);

        $output = $this->createStub(OutputInterface::class);

        $event = new ConsoleCommandEvent($command, $input, $output);

        $listener->onConsoleCommand($event);
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testOnConsoleCommandWithoutCommand(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with('console.begin', []);

        $listener = new ErrorLogListener($logger);

        $input = $this->createStub(InputInterface::class);
        $output = $this->createStub(OutputInterface::class);

        $event = new ConsoleCommandEvent(null, $input, $output);

        $listener->onConsoleCommand($event);
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testOnConsoleCommandWithOptions(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with('console.begin', $this->callback(function (array $context) {
                return $context['cmd'] === 'app:test'
                    && isset($context['input']['custom-option']);
            }));

        $listener = new ErrorLogListener($logger);

        $command = $this->createStub(Command::class);
        $command->method('getName')->willReturn('app:test');

        $input = $this->createStub(InputInterface::class);
        $input->method('getOptions')->willReturn([
            'help' => false,
            'quiet' => false,
            'custom-option' => 'value',
        ]);
        $input->method('getArguments')->willReturn(['command' => 'app:test']);

        $output = $this->createStub(OutputInterface::class);

        $event = new ConsoleCommandEvent($command, $input, $output);

        $listener->onConsoleCommand($event);
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testOnConsoleCommandWithArguments(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with('console.begin', $this->callback(function (array $context) {
                return $context['cmd'] === 'app:test'
                    && isset($context['input']['arguments']['name']);
            }));

        $listener = new ErrorLogListener($logger);

        $command = $this->createStub(Command::class);
        $command->method('getName')->willReturn('app:test');

        $input = $this->createStub(InputInterface::class);
        $input->method('getOptions')->willReturn([]);
        $input->method('getArguments')->willReturn([
            'command' => 'app:test',
            'name' => 'test-arg',
        ]);

        $output = $this->createStub(OutputInterface::class);

        $event = new ConsoleCommandEvent($command, $input, $output);

        $listener->onConsoleCommand($event);
    }

    public function testOnConsoleException(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('log')
            ->with(LogLevel::ERROR, 'console.exception', $this->callback(function (array $context) {
                return $context['exception_class'] === Exception::class
                    && $context['exception_message'] === 'Command failed'
                    && $context['cmd'] === 'app:failing';
            }));

        $listener = new ErrorLogListener($logger);

        $command = $this->createStub(Command::class);
        $command->method('getName')->willReturn('app:failing');

        $input = $this->createStub(InputInterface::class);
        $output = $this->createStub(OutputInterface::class);

        $event = new ConsoleErrorEvent($input, $output, new Exception('Command failed'), $command);

        $listener->onConsoleException($event);
    }

    public function testOnConsoleExceptionWithoutCommand(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('log')
            ->with(LogLevel::ERROR, 'console.exception', $this->callback(function (array $context) {
                return !isset($context['cmd']);
            }));

        $listener = new ErrorLogListener($logger);

        $input = $this->createStub(InputInterface::class);
        $output = $this->createStub(OutputInterface::class);

        $event = new ConsoleErrorEvent($input, $output, new Exception('Error'));

        $listener->onConsoleException($event);
    }

    public function testOnConsoleTerminateSuccess(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with('console.end', $this->callback(function (array $context) {
                return $context['cmd'] === 'app:test';
            }));

        $listener = new ErrorLogListener($logger);

        $command = $this->createStub(Command::class);
        $command->method('getName')->willReturn('app:test');

        $input = $this->createStub(InputInterface::class);
        $output = $this->createStub(OutputInterface::class);

        $event = new ConsoleTerminateEvent($command, $input, $output, 0);

        $listener->onConsoleTerminate($event);
    }

    public function testOnConsoleTerminateWith200(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with('console.end', $this->anything());

        $listener = new ErrorLogListener($logger);

        $command = $this->createStub(Command::class);
        $command->method('getName')->willReturn('app:test');

        $input = $this->createStub(InputInterface::class);
        $output = $this->createStub(OutputInterface::class);

        $event = new ConsoleTerminateEvent($command, $input, $output, 200);

        $listener->onConsoleTerminate($event);
    }

    public function testOnConsoleTerminateWithError(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with('console.error', $this->callback(function (array $context) {
                return $context['cmd'] === 'app:test'
                    && $context['code'] === 1;
            }));

        $listener = new ErrorLogListener($logger);

        $command = $this->createStub(Command::class);
        $command->method('getName')->willReturn('app:test');

        $input = $this->createStub(InputInterface::class);
        $output = $this->createStub(OutputInterface::class);

        $event = new ConsoleTerminateEvent($command, $input, $output, 1);

        $listener->onConsoleTerminate($event);
    }

    public function testOnConsoleTerminateWithDifferentCommand(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with('console.error', $this->callback(function (array $context) {
                // Time should not be set since command name differs from active command
                return $context['cmd'] === 'app:other' && !isset($context['time']);
            }));

        $listener = new ErrorLogListener($logger);

        // Set active command to different name via reflection
        $reflection = new \ReflectionClass(ErrorLogListener::class);
        $activeCommandName = $reflection->getProperty('activeCommandName');
        $activeCommandName->setValue(null, 'app:original');
        $timeCommandBegin = $reflection->getProperty('timeCommandBegin');
        $timeCommandBegin->setValue(null, microtime(true));

        $command = $this->createStub(Command::class);
        $command->method('getName')->willReturn('app:other');

        $input = $this->createStub(InputInterface::class);
        $output = $this->createStub(OutputInterface::class);

        $event = new ConsoleTerminateEvent($command, $input, $output, 1);

        $listener->onConsoleTerminate($event);
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testOnConsoleTerminateWithTime(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        // First call for console.begin
        $logger->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) {
                if ($message === 'console.end') {
                    $this->assertArrayHasKey('time', $context);
                    $this->assertIsFloat($context['time']);
                }
            });

        $listener = new ErrorLogListener($logger);

        $command = $this->createStub(Command::class);
        $command->method('getName')->willReturn('app:test');

        $input = $this->createStub(InputInterface::class);
        $input->method('getOptions')->willReturn([]);
        $input->method('getArguments')->willReturn(['command' => 'app:test']);

        $output = $this->createStub(OutputInterface::class);

        // Start command
        $commandEvent = new ConsoleCommandEvent($command, $input, $output);
        $listener->onConsoleCommand($commandEvent);

        // End command
        $terminateEvent = new ConsoleTerminateEvent($command, $input, $output, 0);
        $listener->onConsoleTerminate($terminateEvent);
    }

    public function testFormatExceptionContext(): void
    {
        $exception = new Exception('Test message');

        $context = ErrorLogListener::formatExceptionContext($exception, ['extra' => 'data']);

        $this->assertEquals(Exception::class, $context['exception_class']);
        $this->assertEquals('Test message', $context['exception_message']);
        $this->assertStringContainsString('.php:', $context['exception_line']);
        $this->assertNotEmpty($context['exception_trace']);
        $this->assertEquals('data', $context['extra']);
    }

    public function testFormatExceptionContextWithNullContext(): void
    {
        $exception = new Exception('Test');

        $context = ErrorLogListener::formatExceptionContext($exception, null);

        $this->assertArrayHasKey('exception_class', $context);
        $this->assertArrayHasKey('exception_message', $context);
    }

    public function testLogException(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('log')
            ->with(LogLevel::ERROR, 'test.error', $this->callback(function (array $context) {
                return $context['exception_message'] === 'Error occurred';
            }));

        ErrorLogListener::logException($logger, 'test.error', new Exception('Error occurred'));
    }

    public function testLogExceptionHandlesLoggerException(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))
            ->method('log')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new Exception('Logger failed')),
                null
            );

        // Should not throw, should fallback to simpler log
        ErrorLogListener::logException($logger, 'test.error', new Exception('Original error'));
        $this->addToAssertionCount(1);
    }

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

    public function testStringifyChargesTheBudgetForInputNotMaskedOutput(): void
    {
        $method = (new \ReflectionClass(ErrorLogListener::class))->getMethod('stringify');
        $long = str_repeat('a', 9000).'@example.com';

        $result = json_decode($method->invoke(null, [$long, $long, $long, $long]), true);

        $this->assertSame('***@example.com', $result[0]);
        $this->assertSame(str_repeat('a', 988), $result[1]);
        $this->assertSame(['', ''], [$result[2], $result[3]]);
    }
}
