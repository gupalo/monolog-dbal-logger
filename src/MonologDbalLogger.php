<?php

namespace Gupalo\MonologDbalLogger;

use Doctrine\DBAL\Connection;
use Monolog\Level;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Throwable;

class MonologDbalLogger extends AbstractProcessingHandler
{
    protected array $levelNames = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];

    protected Connection $connection;

    protected MonologDbalCleaner $cleaner;

    protected string $table;

    protected LogRecord $record;

    protected array $context;

    protected array $additionalFields;

    public function __construct(
        Connection $connection,
        string $table = '_log',
        int $maxRows = 100000,
        $level = Level::Debug,
        $bubble = true,
        MonologDbalCleaner $cleaner = null
    ) {
        $this->connection = $connection;
        $this->table = $table;

        $this->cleaner = $cleaner ?? new MonologDbalCleaner($connection, $table, $maxRows);

        parent::__construct($level, $bubble);
    }

    /**
     * Writes the record down to the log of the implementing handler
     */
    protected function write(LogRecord $record): void
    {
        $this->record = $record;

        if ($this->needSkip()) {
            return;
        }
        $this->cleaner->cleanup();

        $this->initContextAndAdditionalFields();
        $data = $this->getData();

        $this->insert($data);
    }

    protected function needSkip(): bool
    {
        return false;
    }

    protected function initContextAndAdditionalFields(): void
    {
        $this->context = array_merge($this->record->context ?? [], $this->record->extra ?? []);
        $this->additionalFields = [];
    }

    protected function getData(): array
    {
        return array_merge($this->getDefaultData(), $this->getAdditionalData());
    }

    protected function getDefaultData(): array
    {
        return [
            'created_at' => $this->record->datetime->format('Y-m-d H:i:s'),
            'level' => $this->record->level->value,
            'level_name' => $this->normalizeLevelName($this->record->level->name),
            'channel' => $this->leftNull($this->record->channel ?? null, 255),
            'message' => $this->leftNull($this->record->message ?? null, 1024),
            'context' => $this->serializeArrayNull($this->context),
        ];
    }

    protected function getAdditionalData(): array
    {
        return [];
    }

    protected function insert(array $data): void
    {
        try {
            $this->connection->insert($this->table, $data);
        } catch (Throwable) {
        }
    }

    protected function normalizeLevelName(?string $levelName): ?string
    {
        $levelName = strtolower($levelName);
        if (!in_array($levelName, $this->levelNames, true)) {
            $levelName = null;
        }

        return $levelName;
    }

    protected function serializeArrayNull(array $context, int $maxLength = 65536): ?string
    {
        if (empty($context)) {
            return null;
        }

        try {
            $contextSerialized = json_encode($context, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            $contextSerialized = $e->getMessage();
        }

        return mb_substr($contextSerialized, 0, $maxLength);
    }

    protected function leftNull(?string $s, int $maxLength): ?string
    {
        if ($s === '' || $s === null) {
            return null;
        }

        return mb_substr($s, 0, $maxLength);
    }

    protected function intNull(?int $v): ?int
    {
        return ($v !== null) ? (int)$v : null;
    }

    protected function floatNull(?float $v): ?float
    {
        return ($v !== null) ? (float)$v : null;
    }
}
