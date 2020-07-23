<?php

namespace Gupalo\MonologDbalLogger;

use Doctrine\DBAL\Connection;
use Throwable;

class MonologDbalCleaner
{
    private Connection $connection;

    private string $table;

    private int $maxRows;

    public function __construct(
        Connection $connection,
        string $table = '_log',
        int $maxRows = 100000
    ) {
        $this->connection = $connection;
        $this->table = $table;
        $this->maxRows = $maxRows;
    }

    public function cleanup(bool $force = false): void
    {
        if ($this->needRun($force)) {
            $this->run();
        }
    }

    protected function needRun(bool $force): bool
    {
        return $force || time() % 1000 === 0;
    }

    protected function run(): void
    {
        try {
            $maxId = $this->getMaxId();
            $this->deleteWhereIdLessThan($maxId);
        } catch (Throwable $e) {
        }
    }

    protected function getMaxId(): int
    {
        return (int)$this->connection->createQueryBuilder()
            ->from($this->table)
            ->select('id')
            ->setFirstResult($this->maxRows)
            ->setMaxResults(1)
            ->orderBy('id', 'DESC')
            ->execute()
            ->fetchColumn(0);
    }

    protected function deleteWhereIdLessThan($maxId): void
    {
        if ($maxId > 0) {
            $this->connection->createQueryBuilder()
                ->delete($this->table)
                ->andWhere('id < :max_id')
                ->setParameter('max_id', $maxId)
                ->execute();
        }
    }
}
