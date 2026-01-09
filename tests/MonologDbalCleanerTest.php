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

        $deleteQueryBuilder = $this->createMock(QueryBuilder::class);
        $deleteQueryBuilder->method('delete')->with('_log')->willReturnSelf();
        $deleteQueryBuilder->method('andWhere')->with('id < :max_id')->willReturnSelf();
        $deleteQueryBuilder->method('setParameter')->with('max_id', 12345)->willReturnSelf();
        $deleteQueryBuilder->expects($this->once())->method('executeStatement');

        $connection = $this->createMock(Connection::class);
        $connection->method('createQueryBuilder')
            ->willReturnOnConsecutiveCalls($selectQueryBuilder, $deleteQueryBuilder);

        $cleaner = new MonologDbalCleaner($connection, '_log', 1000);
        $cleaner->cleanup(force: true);
    }

    public function testCleanupForceWithZeroMaxIdDoesNotDelete(): void
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

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('createQueryBuilder')->willReturn($selectQueryBuilder);

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

        $deleteQueryBuilder = $this->createMock(QueryBuilder::class);
        $deleteQueryBuilder->method('delete')->with('custom_table')->willReturnSelf();
        $deleteQueryBuilder->method('andWhere')->willReturnSelf();
        $deleteQueryBuilder->method('setParameter')->willReturnSelf();
        $deleteQueryBuilder->method('executeStatement');

        $connection = $this->createMock(Connection::class);
        $connection->method('createQueryBuilder')
            ->willReturnOnConsecutiveCalls($selectQueryBuilder, $deleteQueryBuilder);

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

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(2))->method('createQueryBuilder')->willReturn($selectQueryBuilder);

        $cleaner = new MonologDbalCleaner($connection, '_log', 1000);
        $cleaner->cleanup(force: true);
        $cleaner->cleanup(force: true);
    }
}
