<?php

namespace Gupalo\MonologDbalLogger\Tests;

use Exception;
use Gupalo\MonologDbalLogger\Symfony\ErrorLogListener;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
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
            ->method('error')
            ->with('exception', $this->callback(function (array $context) {
                return $context['exception_class'] === Exception::class
                    && $context['exception_message'] === 'Test error'
                    && $context['ip'] === '127.0.0.1'
                    && $context['url'] === 'http://localhost/test';
            }));

        $listener = new ErrorLogListener($logger);

        $request = Request::create('http://localhost/test', 'GET');
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, new Exception('Test error'));

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

        $command = $this->createMock(Command::class);
        $command->method('getName')->willReturn('app:test');

        $input = $this->createMock(InputInterface::class);
        $input->method('getOptions')->willReturn([]);
        $input->method('getArguments')->willReturn(['command' => 'app:test']);

        $output = $this->createMock(OutputInterface::class);

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

        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);

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

        $command = $this->createMock(Command::class);
        $command->method('getName')->willReturn('app:test');

        $input = $this->createMock(InputInterface::class);
        $input->method('getOptions')->willReturn([
            'help' => false,
            'quiet' => false,
            'custom-option' => 'value',
        ]);
        $input->method('getArguments')->willReturn(['command' => 'app:test']);

        $output = $this->createMock(OutputInterface::class);

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

        $command = $this->createMock(Command::class);
        $command->method('getName')->willReturn('app:test');

        $input = $this->createMock(InputInterface::class);
        $input->method('getOptions')->willReturn([]);
        $input->method('getArguments')->willReturn([
            'command' => 'app:test',
            'name' => 'test-arg',
        ]);

        $output = $this->createMock(OutputInterface::class);

        $event = new ConsoleCommandEvent($command, $input, $output);

        $listener->onConsoleCommand($event);
    }

    public function testOnConsoleException(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('console.exception', $this->callback(function (array $context) {
                return $context['exception_class'] === Exception::class
                    && $context['exception_message'] === 'Command failed'
                    && $context['cmd'] === 'app:failing';
            }));

        $listener = new ErrorLogListener($logger);

        $command = $this->createMock(Command::class);
        $command->method('getName')->willReturn('app:failing');

        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);

        $event = new ConsoleErrorEvent($input, $output, new Exception('Command failed'), $command);

        $listener->onConsoleException($event);
    }

    public function testOnConsoleExceptionWithoutCommand(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('console.exception', $this->callback(function (array $context) {
                return !isset($context['cmd']);
            }));

        $listener = new ErrorLogListener($logger);

        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);

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

        $command = $this->createMock(Command::class);
        $command->method('getName')->willReturn('app:test');

        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);

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

        $command = $this->createMock(Command::class);
        $command->method('getName')->willReturn('app:test');

        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);

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

        $command = $this->createMock(Command::class);
        $command->method('getName')->willReturn('app:test');

        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);

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

        $command = $this->createMock(Command::class);
        $command->method('getName')->willReturn('app:other');

        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);

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

        $command = $this->createMock(Command::class);
        $command->method('getName')->willReturn('app:test');

        $input = $this->createMock(InputInterface::class);
        $input->method('getOptions')->willReturn([]);
        $input->method('getArguments')->willReturn(['command' => 'app:test']);

        $output = $this->createMock(OutputInterface::class);

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
            ->method('error')
            ->with('test.error', $this->callback(function (array $context) {
                return $context['exception_message'] === 'Error occurred';
            }));

        ErrorLogListener::logException($logger, 'test.error', new Exception('Error occurred'));
    }

    public function testLogExceptionHandlesLoggerException(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))
            ->method('error')
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
}
