<?php

namespace Gupalo\MonologDbalLogger\Tests;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Gupalo\MonologDbalLogger\MonologDbalCleaner;
use PHPUnit\Framework\TestCase;

class MonologDbalCleanerTest extends TestCase
{
    public function testCleanupForceDeletesOldRows(): void
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchOne')->willReturn(12345);

        $selectQueryBuilder = $this->createMock(QueryBuilder::class);
        $selectQueryBuilder->method('from')->willReturnSelf();
        $selectQueryBuilder->method('select')->willReturnSelf();
        $selectQueryBuilder->method('setFirstResult')->with(1000)->willReturnSelf();
        $selectQueryBuilder->method('setMaxResults')->with(1)->willReturnSelf();
        $selectQueryBuilder->method('orderBy')->with('id', 'DESC')->willReturnSelf();
        $selectQueryBuilder->method('executeQuery')->willReturn($result);

        $deleteQueryBuilder = $this->createDeleteQueryBuilder();
        $deleteQueryBuilder->expects($this->once())->method('executeStatement');

        $ageDeleteBuilders = $this->createAgeDeleteQueryBuilders(3);

        $connection = $this->createMock(Connection::class);
        $connection->method('createQueryBuilder')
            ->willReturnOnConsecutiveCalls($selectQueryBuilder, $deleteQueryBuilder, ...$ageDeleteBuilders);

        $cleaner = new MonologDbalCleaner($connection, '_log', 1000);
        $cleaner->cleanup(force: true);
    }

    public function testCleanupForceWithZeroMaxIdDoesNotDeleteByMaxRows(): void
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchOne')->willReturn(0);

        $selectQueryBuilder = $this->createMock(QueryBuilder::class);
        $selectQueryBuilder->method('from')->willReturnSelf();
        $selectQueryBuilder->method('select')->willReturnSelf();
        $selectQueryBuilder->method('setFirstResult')->willReturnSelf();
        $selectQueryBuilder->method('setMaxResults')->willReturnSelf();
        $selectQueryBuilder->method('orderBy')->willReturnSelf();
        $selectQueryBuilder->method('executeQuery')->willReturn($result);

        // 3 age-based deletes still run
        $ageDeleteBuilders = $this->createAgeDeleteQueryBuilders(3);

        $connection = $this->createMock(Connection::class);
        $connection->method('createQueryBuilder')
            ->willReturnOnConsecutiveCalls($selectQueryBuilder, ...$ageDeleteBuilders);

        $cleaner = new MonologDbalCleaner($connection, '_log', 1000);
        $cleaner->cleanup(force: true);
    }

    public function testCleanupWithoutForceDoesNotRunImmediately(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('createQueryBuilder');

        $cleaner = new MonologDbalCleaner($connection, '_log', 1000);

        // Run multiple times - due to probability (1/1000), it's very unlikely to trigger
        for ($i = 0; $i < 10; $i++) {
            $cleaner->cleanup(force: false);
        }

        $this->addToAssertionCount(1);
    }

    public function testCleanupWithCustomTable(): void
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchOne')->willReturn(100);

        $selectQueryBuilder = $this->createMock(QueryBuilder::class);
        $selectQueryBuilder->method('from')->with('custom_table')->willReturnSelf();
        $selectQueryBuilder->method('select')->willReturnSelf();
        $selectQueryBuilder->method('setFirstResult')->willReturnSelf();
        $selectQueryBuilder->method('setMaxResults')->willReturnSelf();
        $selectQueryBuilder->method('orderBy')->willReturnSelf();
        $selectQueryBuilder->method('executeQuery')->willReturn($result);

        $deleteQueryBuilder = $this->createDeleteQueryBuilder('custom_table');
        $ageDeleteBuilders = $this->createAgeDeleteQueryBuilders(3, 'custom_table');

        $connection = $this->createMock(Connection::class);
        $connection->method('createQueryBuilder')
            ->willReturnOnConsecutiveCalls($selectQueryBuilder, $deleteQueryBuilder, ...$ageDeleteBuilders);

        $cleaner = new MonologDbalCleaner($connection, 'custom_table', 500);
        $cleaner->cleanup(force: true);
    }

    public function testCleanupHandlesExceptionGracefully(): void
    {
        $selectQueryBuilder = $this->createMock(QueryBuilder::class);
        $selectQueryBuilder->method('from')->willReturnSelf();
        $selectQueryBuilder->method('select')->willReturnSelf();
        $selectQueryBuilder->method('setFirstResult')->willReturnSelf();
        $selectQueryBuilder->method('setMaxResults')->willReturnSelf();
        $selectQueryBuilder->method('orderBy')->willReturnSelf();
        $selectQueryBuilder->method('executeQuery')->willThrowException(new \Exception('DB error'));

        $connection = $this->createMock(Connection::class);
        $connection->method('createQueryBuilder')->willReturn($selectQueryBuilder);

        $cleaner = new MonologDbalCleaner($connection, '_log', 1000);

        // Should not throw
        $cleaner->cleanup(force: true);
        $this->addToAssertionCount(1);
    }

    public function testSecondForceCleanupRunsAgain(): void
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchOne')->willReturn(0);

        $selectQueryBuilder = $this->createMock(QueryBuilder::class);
        $selectQueryBuilder->method('from')->willReturnSelf();
        $selectQueryBuilder->method('select')->willReturnSelf();
        $selectQueryBuilder->method('setFirstResult')->willReturnSelf();
        $selectQueryBuilder->method('setMaxResults')->willReturnSelf();
        $selectQueryBuilder->method('orderBy')->willReturnSelf();
        $selectQueryBuilder->method('executeQuery')->willReturn($result);

        $ageDeleteBuilder = $this->createDeleteQueryBuilder();

        $connection = $this->createMock(Connection::class);
        // 2 runs: each has 1 select + 3 age deletes = 8 total
        $connection->expects($this->exactly(8))->method('createQueryBuilder')
            ->willReturn($selectQueryBuilder, $ageDeleteBuilder, $ageDeleteBuilder, $ageDeleteBuilder, $selectQueryBuilder, $ageDeleteBuilder, $ageDeleteBuilder, $ageDeleteBuilder);

        $cleaner = new MonologDbalCleaner($connection, '_log', 1000);
        $cleaner->cleanup(force: true);
        $cleaner->cleanup(force: true);
    }

    public function testCleanupWithDisabledRetention(): void
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchOne')->willReturn(500);

        $selectQueryBuilder = $this->createMock(QueryBuilder::class);
        $selectQueryBuilder->method('from')->willReturnSelf();
        $selectQueryBuilder->method('select')->willReturnSelf();
        $selectQueryBuilder->method('setFirstResult')->willReturnSelf();
        $selectQueryBuilder->method('setMaxResults')->willReturnSelf();
        $selectQueryBuilder->method('orderBy')->willReturnSelf();
        $selectQueryBuilder->method('executeQuery')->willReturn($result);

        $deleteQueryBuilder = $this->createDeleteQueryBuilder();

        $connection = $this->createMock(Connection::class);
        // select + maxRows delete only, no age-based deletes
        $connection->expects($this->exactly(2))->method('createQueryBuilder')
            ->willReturnOnConsecutiveCalls($selectQueryBuilder, $deleteQueryBuilder);

        $cleaner = new MonologDbalCleaner($connection, '_log', 1000, infoRetentionDays: 0, warningRetentionDays: 0, errorRetentionDays: 0);
        $cleaner->cleanup(force: true);
    }

    private function createDeleteQueryBuilder(string $table = '_log'): QueryBuilder
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('delete')->with($table)->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('executeStatement');

        return $qb;
    }

    /** @return QueryBuilder[] */
    private function createAgeDeleteQueryBuilders(int $count, string $table = '_log'): array
    {
        $builders = [];
        for ($i = 0; $i < $count; $i++) {
            $builders[] = $this->createDeleteQueryBuilder($table);
        }

        return $builders;
    }
}
