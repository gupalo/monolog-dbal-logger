<?php

namespace Gupalo\MonologDbalLogger;

use ErrorException;
use Throwable;

class MyMonologDbalLogger extends MonologDbalLogger
{
    private static array $skipMessages = [
        'User Deprecated: The "Doctrine\\Common\\Inflector\\Inflector::classify" method is deprecated and will be dropped in doctrine/inflector 2.0. Please update to the new Inflector API.',
    ];

    protected function needSkip(): bool
    {
        if (in_array($this->record->message, self::$skipMessages, true)) {
            return true;
        }

        $e = $this->record->context['exception'] ?? null;

        return ($e instanceof ErrorException && $e->getSeverity() === E_USER_DEPRECATED);
    }

    protected function initContextAndAdditionalFields(): void
    {
        parent::initContextAndAdditionalFields();

        $this->tryFixCmd();
        $this->tryInitException();
        $this->tryInitAdditionalFields();
    }

    protected function tryFixCmd(): void
    {
        try {
            $this->fixCmd();
        } catch (Throwable $e) {
        }
    }

    protected function tryInitException(): void
    {
        try {
            $this->initException();
        } catch (Throwable $e) {
        }
    }

    protected function tryInitAdditionalFields(): void
    {
        try {
            $this->initAdditionalFields();
        } catch (Throwable $e) {
        }
    }

    protected function getAdditionalData(): array
    {
        return [
            'cmd' => $this->leftNull($this->additionalFields['cmd'] ?? null, 255),
            'method' => $this->leftNull($this->additionalFields['method'] ?? null, 255),
            'uid' => $this->leftNull($this->additionalFields['uid'] ?? null, 32),
            'count' => $this->intNull($this->additionalFields['count'] ?? null),
            'time' => $this->floatNull($this->additionalFields['time'] ?? null),
            'exception_class' => $this->leftNull($this->additionalFields['exception_class'] ?? null, 1024),
            'exception_message' => $this->leftNull($this->additionalFields['exception_message'] ?? null, 1024),
            'exception_line' => $this->leftNull($this->additionalFields['exception_line'] ?? null, 1024),
            'exception_trace' => $this->leftNull($this->additionalFields['exception_trace'] ?? null, 65536),
        ];
    }

    protected function fixCmd(): void
    {
        if ($this->record->message === 'cmd' && empty($this->context['cmd']) && !empty($this->context['name'])) {
            $this->additionalFields['cmd'] = $this->context['name'];
            unset($this->context['name']);
        }
    }

    protected function initException(): void
    {
        $data = ['exception_class' => null, 'exception_message' => null, 'exception_line' => null, 'exception_trace' => null];
        if (isset($this->context['exception']) && $this->context['exception'] instanceof Throwable) {
            $e = $this->context['exception'];
            $data['exception_class'] = get_class($e);
            $data['exception_message'] = $e->getMessage();
            $data['exception_line'] = sprintf('%s:%s', $e->getFile(), $e->getLine());
            $data['exception_trace'] = $e->getTraceAsString();
            unset($this->context['exception']);
        }
        $keys = array_keys($data);
        foreach ($keys as $key) {
            if (isset($this->context[$key])) {
                $data[$key] = $this->context[$key];
                unset($this->context[$key]);
            }
        }

        $this->additionalFields = array_merge($data, $this->additionalFields);
    }

    protected function initAdditionalFields(): void
    {
        $fieldNames = ['cmd', 'method', 'uid', 'count', 'time'];
        foreach ($fieldNames as $field) {
            $this->additionalFields[$field] = $this->context[$field] ?? null;
            unset($this->context[$field]);
        }
    }
}
