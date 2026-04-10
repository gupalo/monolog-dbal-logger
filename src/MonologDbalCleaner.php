<?php /** @noinspection PhpDqlBuilderUnknownModelInspection */

namespace Gupalo\MonologDbalLogger;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Throwable;

class MonologDbalCleaner
{
    private Connection $connection;

    private string $table;

    private int $maxRows;

    private int $infoRetentionDays;

    private int $warningRetentionDays;

    private int $errorRetentionDays;

    private int $lastTimeCleaned = 0;

    public function __construct(
        Connection $connection,
        string $table = '_log',
        int $maxRows = 100000,
        int $infoRetentionDays = 1,
        int $warningRetentionDays = 7,
        int $errorRetentionDays = 30,
    ) {
        $this->connection = $connection;
        $this->table = $table;
        $this->maxRows = $maxRows;
        $this->infoRetentionDays = $infoRetentionDays;
        $this->warningRetentionDays = $warningRetentionDays;
        $this->errorRetentionDays = $errorRetentionDays;
    }

    /** @noinspection PhpUnhandledExceptionInspection */
    public function cleanup(bool $force = false): void
    {
        if ($this->needRun($force)) {
            if ($force || random_int(0, 100) === 0) {
                $this->run();
            }
            $this->lastTimeCleaned = time();
        }
    }

    protected function needRun(bool $force): bool
    {
        return ($force || $this->isTimeToClean());
    }

    protected function isTimeToClean(): bool
    {
        $time = time();

        return (
            $time - $this->lastTimeCleaned >= 3600 &&
            ((int) round(microtime(true) * 1000)) % 1000 === 0
        );
    }

    protected function run(): void
    {
        try {
            $this->deleteInfoBeyondMaxRows();
            $this->deleteByAge();
        } catch (Throwable) {
        }
    }

    /** @throws Exception */
    protected function deleteInfoBeyondMaxRows(): void
    {
        $maxId = (int)$this->connection->createQueryBuilder()
            ->from($this->table)
            ->select('id')
            ->setFirstResult($this->maxRows)
            ->setMaxResults(1)
            ->orderBy('id', 'DESC')
            ->executeQuery()
            ->fetchOne();

        if ($maxId > 0) {
            $this->connection->createQueryBuilder()
                ->delete($this->table)
                ->andWhere('id < :max_id')
                ->andWhere('level < 250')
                ->setParameter('max_id', $maxId)
                ->executeStatement();
        }
    }

    /** @throws Exception */
    protected function deleteByAge(): void
    {
        if ($this->infoRetentionDays > 0) {
            $this->connection->createQueryBuilder()
                ->delete($this->table)
                ->andWhere('level < 250')
                ->andWhere('created_at < :cutoff')
                ->setParameter('cutoff', date('Y-m-d H:i:s', strtotime(sprintf('-%d days', $this->infoRetentionDays))))
                ->executeStatement();
        }

        if ($this->warningRetentionDays > 0) {
            $this->connection->createQueryBuilder()
                ->delete($this->table)
                ->andWhere('level >= 250')
                ->andWhere('level < 400')
                ->andWhere('created_at < :cutoff')
                ->setParameter('cutoff', date('Y-m-d H:i:s', strtotime(sprintf('-%d days', $this->warningRetentionDays))))
                ->executeStatement();
        }

        if ($this->errorRetentionDays > 0) {
            $this->connection->createQueryBuilder()
                ->delete($this->table)
                ->andWhere('level >= 400')
                ->andWhere('created_at < :cutoff')
                ->setParameter('cutoff', date('Y-m-d H:i:s', strtotime(sprintf('-%d days', $this->errorRetentionDays))))
                ->executeStatement();
        }
    }
}
