<?php

namespace Gupalo\MonologDbalLogger\Symfony;

use Monolog\ErrorHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

class ErrorLogListener implements EventSubscriberInterface
{
    private LoggerInterface $logger;

    private static ?string $activeCommandName = null;

    private static ?float $timeCommandBegin = null;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
            KernelEvents::EXCEPTION => 'onKernelException',
            ConsoleEvents::COMMAND => 'onConsoleCommand',
            ConsoleEvents::ERROR => 'onConsoleException',
            ConsoleEvents::TERMINATE => 'onConsoleTerminate',
        ];
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function onKernelRequest(RequestEvent $event): void
    {
        $this->registerErrorHandler();
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        self::logException($this->logger, 'exception', $exception, [
            'ip' => $request->getClientIp(),
            'url' => $request->getUri(),
            'query' => self::stringify($request->query->all()),
            'request' => self::stringify($request->request->all()),
        ]);
    }

    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        $this->registerErrorHandler();

        $command = $event->getCommand();
        $input = $event->getInput();

        if (!$command) {
            $this->logger->info('console.begin', []);

            return;
        }

        self::$activeCommandName = $command->getName();
        self::$timeCommandBegin = microtime(true);
        $context = $this->getCommandContext($command, $input);
        try {
            $this->logger->info('console.begin', $context);
        } catch (Throwable $e) {
        }
    }

    public function onConsoleException(ConsoleErrorEvent $event): void
    {
        $command = $event->getCommand();
        $exception = $event->getError();

        $context = [];
        if ($command) {
            $context['cmd'] = $command->getName();
        }

        self::logException($this->logger, 'console.exception', $exception, $context);
    }

    public function onConsoleTerminate(ConsoleTerminateEvent $event): void
    {
        $statusCode = $event->getExitCode();
        $command = $event->getCommand();

        $context = $this->getCommandEndContext($command);

        try {
            if ($statusCode === 0 || $statusCode === 200 || !$command) {
                $this->logger->info('console.end', $context);

                return;
            }

            $this->logger->warning('console.error', array_merge($context, ['code' => $statusCode]));
        } catch (Throwable $e) {
        }
    }

    public static function logException(
        LoggerInterface $logger,
        string $name,
        Throwable $exception,
        array $context = null
    ): void {
        try {
            $logger->error($name, self::formatExceptionContext($exception, $context));
        } catch (Throwable $e) {
            try {
                $logger->error($name, ['exception_message' => $e->getMessage()]);
            } catch (Throwable $e) {
            }
        }
    }

    public static function formatExceptionContext(Throwable $exception, array $context = null): array
    {
        return array_merge([
            'exception_class' => get_class($exception),
            'exception_message' => $exception->getMessage(),
            'exception_line' => sprintf('%s:%s', $exception->getFile(), $exception->getLine()),
            'exception_trace' => $exception->getTraceAsString(),
        ], $context ?? []);
    }

    private static function stringify($data): string
    {
        if (!is_string($data)) {
            try {
                $data = json_encode($data, JSON_THROW_ON_ERROR, 10);
            } catch (Throwable $e) {
                $data = (string)$data;
            }
        }
        $result = substr($data, 0, 10000);

        if (strpos($result, '@') !== false) {
            if (strpos($result, '://') !== false) {
                $result = preg_replace('#(://[^\s@:]+:)[^\s@:]+@#', '$1***@', $result);
            }
            $result = preg_replace('#[a-zA-Z\d.\-_]+(@[a-zA-Z\d]+([\-.][a-z\d]+)*\.[a-z]{2,6})#', '***$1', $result);
        }

        return $result;
    }

    private function getCommandContext(Command $command, InputInterface $input): array
    {
        try {
            $context = [
                'cmd' => $command->getName(),
            ];
            $options = $input->getOptions();
            unset(
                $options['help'],
                $options['quiet'],
                $options['verbose'],
                $options['version'],
                $options['ansi'],
                $options['no-ansi'],
                $options['no-interaction'],
                $options['env'],
                $options['no-debug'],
                $options['env']
            );
            $arguments = $input->getArguments();
            unset($arguments['command']);

            if (!empty($arguments)) {
                $options['arguments'] = $arguments;
            }
            if (!empty($options)) {
                $context['input'] = $options;

                return $context;
            }
        } catch (Throwable $e) {
            $context = [];
        }

        return $context;
    }

    private function registerErrorHandler(): void
    {
        static $isRegistered = false;
        if (!$isRegistered) {
            ErrorHandler::register($this->logger);
            $isRegistered = true;
        }
    }

    protected function getCommandEndContext(?Command $command): array
    {
        $context = [];
        if ($command) {
            $context['cmd'] = $command->getName();
            if (self::$timeCommandBegin && $command->getName() === self::$activeCommandName) {
                $context['time'] = round(microtime(true) - self::$timeCommandBegin, 6);
            }
        }
        self::$activeCommandName = null;
        self::$timeCommandBegin = null;

        return $context;
    }
}
