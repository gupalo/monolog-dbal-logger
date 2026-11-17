<?php

namespace Gupalo\MonologDbalLogger\Symfony;

use Monolog\ErrorHandler;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
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

        // Client errors (404 from scanners, 405, 429, ...) are expected traffic, not server faults:
        // keep them in the log, but below ERROR so they don't reach error trackers like Sentry.
        $isClientError = $exception instanceof HttpExceptionInterface && $exception->getStatusCode() < 500;

        self::logException($this->logger, 'exception', $exception, [
            'ip' => $request->getClientIp(),
            'url' => $request->getUri(),
            'query' => self::stringify($request->query->all()),
            'request' => self::stringify($request->request->all()),
        ], $isClientError ? LogLevel::WARNING : LogLevel::ERROR);
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
        } catch (Throwable) {
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
        } catch (Throwable) {
        }
    }

    public static function logException(
        LoggerInterface $logger,
        string $name,
        Throwable $exception,
        ?array $context = null,
        string $level = LogLevel::ERROR,
    ): void {
        try {
            $logger->log($level, $name, self::formatExceptionContext($exception, $context));
        } catch (Throwable $e) {
            try {
                $logger->log($level, $name, ['exception_message' => $e->getMessage()]);
            } catch (Throwable) {
            }
        }
    }

    public static function formatExceptionContext(Throwable $exception, ?array $context = null): array
    {
        return array_merge([
            'exception_class' => get_class($exception),
            'exception_message' => $exception->getMessage(),
            'exception_line' => sprintf('%s:%s', $exception->getFile(), $exception->getLine()),
            'exception_trace' => $exception->getTraceAsString(),
        ], $context ?? []);
    }

    private const int MAX_LENGTH = 10000;

    private static function stringify($data): string
    {
        if (is_string($data)) {
            return self::mask(substr($data, 0, self::MAX_LENGTH));
        }

        // Mask keys and values one by one, never the encoded JSON: a pattern run over the JSON
        // could match across neighbouring values, and json_encode's escaping (\" in a password)
        // could hide a credential from it. Parameter names are user input too, so keys are masked,
        // and a JsonSerializable is expanded first so its output is masked like anything else.
        // The work stays bounded like before (at most MAX_LENGTH bytes of strings are masked).
        //
        // Scanner probes send invalid UTF-8 and deeply nested params: substitute bad bytes instead
        // of failing, and never cast an array to string ("Array to string conversion" used to be
        // raised from inside this exception logger). Nothing may escape from here: it runs while
        // building the exception log context.
        try {
            $budget = self::MAX_LENGTH;
            $encoded = json_encode(self::sanitize($data, $budget), JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES, 10);
            $error = json_last_error_msg();
        } catch (Throwable $e) {
            $encoded = false;
            $error = $e->getMessage();
        }
        if (false === $encoded) {
            return sprintf('[unencodable %s: %s]', get_debug_type($data), $error);
        }

        return substr($encoded, 0, self::MAX_LENGTH);
    }

    private static function sanitize(mixed $value, int &$budget, int $depth = 0): mixed
    {
        if ($depth > 64) {
            return '[too deep]';
        }
        if ($value instanceof \JsonSerializable) {
            return self::sanitize($value->jsonSerialize(), $budget, $depth + 1);
        }
        if (is_string($value)) {
            $value = $budget > 0 ? self::mask(substr($value, 0, $budget)) : '';
            $budget -= strlen($value);

            return $value;
        }
        if (is_object($value)) {
            return sprintf('[%s]', get_debug_type($value));
        }
        if (!is_array($value)) {
            return $value;
        }

        $result = [];
        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $key = self::sanitize($key, $budget, $depth + 1);
            }
            $result[$key] = self::sanitize($item, $budget, $depth + 1);
        }

        return $result;
    }

    private static function mask(string $value): string
    {
        if (!str_contains($value, '@')) {
            return $value;
        }
        if (str_contains($value, '://')) {
            // null on a PCRE failure (backtrack limit, ...): never let a value through unmasked.
            $value = preg_replace('#(://[^\s@:]+:)[^\s@:]+@#', '$1***@', $value) ?? '[mask failed]';
        }

        return self::maskEmails($value);
    }

    private static function maskEmails(string $value): string
    {
        if (!str_contains($value, '@')) {
            return $value;
        }

        return preg_replace('#[a-zA-Z\d.\-_]+(@[a-zA-Z\d]+([\-.][a-z\d]+)*\.[a-z]{2,6})#', '***$1', $value) ?? '[mask failed]';
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
        } catch (Throwable) {
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
