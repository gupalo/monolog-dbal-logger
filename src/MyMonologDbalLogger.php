<?php

namespace Gupalo\MonologDbalLogger;

use ErrorException;
use Throwable;

class MyMonologDbalLogger extends MonologDbalLogger
{
    protected function needSkip(): bool
    {
        $e = $this->record['context']['exception'] ?? null;

        return ($e && $e instanceof ErrorException && $e->getSeverity() === E_USER_DEPRECATED);
    }

    protected function initContextAndAdditionalFields(): void
    {
        parent::initContextAndAdditionalFields();

        $this->fixCmd();
        $this->initException();
        $this->initAdditionalFields();
    }

    protected function getAdditionalData(): array
    {
        return [
            'cmd' => $this->leftNull($this->context['cmd'] ?? null, 255),
            'method' => $this->leftNull($this->context['method'] ?? null, 255),
            'uid' => $this->leftNull($this->context['uid'] ?? null, 32),
            'count' => $this->intNull($this->context['count'] ?? null),
            'time' => $this->floatNull($this->context['time'] ?? null),
            'exception_class' => $this->leftNull($this->context['exception_class'] ?? null, 1024),
            'exception_message' => $this->leftNull($this->context['exception_message'] ?? null, 1024),
            'exception_line' => $this->leftNull($this->context['exception_line'] ?? null, 1024),
            'exception_trace' => $this->leftNull($this->context['exception_trace'] ?? null, 65536),
        ];
    }

    protected function fixCmd(): void
    {
        if (($this->record['message'] ?? '') === 'cmd' && empty($this->context['cmd']) && !empty($this->context['name'])) {
            $this->additionalFields['cmd'] = $this->context['name'];
            unset($this->context['name']);
        }
    }

    protected function initException(): void
    {
        $data = ['exception_class' => null, 'exception_message' => null, 'exception_line' => null, 'exception_trace' => null];
        if (isset($this->additionalFields['exception']) && $this->additionalFields['exception'] instanceof Throwable) {
            $e = $this->additionalFields['exception'];
            $data['exception_class'] = get_class($e);
            $data['exception_message'] = $e->getMessage();
            $data['exception_line'] = sprintf('%s:%s', $e->getFile(), $e->getLine());
            $data['exception_trace'] = $e->getTraceAsString();
            unset($this->additionalFields['exception']);
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
