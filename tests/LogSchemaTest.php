<?php

namespace Gupalo\MonologDbalLogger\Tests;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Gupalo\MonologDbalLogger\Entity\Log;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The Log entity must map the `_log` table the logger writes to (README / LOG_TABLE_DDL below),
 * so that `doctrine:schema:update` in an app prints nothing for `_log`. Drift there is dangerous:
 * applying it made columns the logger fills with NULL NOT NULL, and insert() swallows the error.
 */
class LogSchemaTest extends TestCase
{
    public const string LOG_TABLE_DDL = <<<'SQL'
        CREATE TABLE `_log` (
            `id`                    bigint UNSIGNED NOT NULL AUTO_INCREMENT,
            `created_at`            datetime NOT NULL,
            `level_name`            enum('debug','info','notice','warning','error','critical','alert','emergency') NULL DEFAULT NULL,
            `message`               varchar(1024) NOT NULL DEFAULT '',
            `context`               text NULL DEFAULT NULL,
            `channel`               varchar(255) NOT NULL DEFAULT '',
            `cmd`                   varchar(255) NULL DEFAULT NULL,
            `method`                varchar(255) NULL DEFAULT NULL,
            `uid`                   varchar(32) NULL DEFAULT NULL,
            `count`                 int NULL DEFAULT NULL,
            `time`                  float NULL DEFAULT NULL,
            `level`                 smallint NOT NULL DEFAULT 0,
            `exception_class`       varchar(1024) NULL DEFAULT NULL,
            `exception_message`     varchar(1024) NULL DEFAULT NULL,
            `exception_line`        varchar(1024) NULL DEFAULT NULL,
            `exception_trace`       text NULL,
            PRIMARY KEY (`id`),
            INDEX `log_created_at_level` (`created_at`, `level`) USING BTREE
        ) DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_general_ci
        SQL;

    private const string SERVER_VERSION = '10.11.7-MariaDB';

    /** Env var with a MariaDB/MySQL server URL (no database); the DB test creates and drops its own database. */
    private const string DATABASE_URL_ENV = 'MONOLOG_DBAL_LOGGER_TEST_DATABASE_URL';

    /**
     * No database needed: the table built from the entity metadata is compared with the table that
     * DBAL introspects from LOG_TABLE_DDL on MariaDB (built here by hand, verified by the DB test).
     */
    public function testMappingMatchesLogTable(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => self::SERVER_VERSION]);
        $em = $this->createEntityManager($connection);

        $schema = (new SchemaTool($em))->getSchemaFromMetadata([$em->getClassMetadata(Log::class)]);
        $mapped = $schema->getTable('_log');

        $diff = (new Comparator($connection->getDatabasePlatform()))->compareTables(self::introspectedLogTable(), $mapped);

        self::assertTrue($diff->isEmpty(), 'Log entity mapping drifted from the _log table: ' . implode('; ', $connection->getDatabasePlatform()->getAlterTableSQL($diff)));
        self::assertTrue($mapped->hasIndex('log_created_at_level'));
        self::assertSame(['created_at', 'level'], $mapped->getIndex('log_created_at_level')->getColumns());
    }

    /**
     * Creates a scratch database, creates `_log` from LOG_TABLE_DDL in it, and asserts that the ORM's
     * update SQL is empty. Skipped unless MONOLOG_DBAL_LOGGER_TEST_DATABASE_URL is set, e.g.
     * mysql://root:mypass@127.0.0.1:3306/?serverVersion=10.11.7-MariaDB
     */
    #[Group('db')]
    public function testSchemaUpdateIsEmptyAgainstRealLogTable(): void
    {
        $url = getenv(self::DATABASE_URL_ENV);
        if (!is_string($url) || $url === '') {
            self::markTestSkipped(self::DATABASE_URL_ENV . ' is not set');
        }

        $params = (new DsnParser(['mysql' => 'pdo_mysql', 'mariadb' => 'pdo_mysql']))->parse($url);
        unset($params['dbname']);
        $params['serverVersion'] ??= self::SERVER_VERSION;
        $params['charset'] ??= 'utf8mb4';

        $dbName = sprintf('mdl_schema_test_%s', bin2hex(random_bytes(4)));
        $server = DriverManager::getConnection($params);
        $server->executeStatement(sprintf('CREATE DATABASE `%s`', $dbName));
        try {
            $connection = DriverManager::getConnection([
                ...$params,
                'dbname' => $dbName,
                'defaultTableOptions' => ['charset' => 'utf8mb4', 'collate' => 'utf8mb4_unicode_ci'],
            ]);
            $connection->executeStatement(self::LOG_TABLE_DDL);

            $em = $this->createEntityManager($connection);
            $sql = (new SchemaTool($em))->getUpdateSchemaSql([$em->getClassMetadata(Log::class)]);
            $connection->close();

            self::assertSame([], $sql);
        } finally {
            $server->executeStatement(sprintf('DROP DATABASE `%s`', $dbName));
            $server->close();
        }
    }

    private function createEntityManager(Connection $connection): EntityManager
    {
        $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__) . '/src/Entity'], true);
        $config->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER));
        $config->enableNativeLazyObjects(true);

        return new EntityManager($connection, $config);
    }

    /** What DBAL's MariaDB schema manager returns for LOG_TABLE_DDL. */
    private static function introspectedLogTable(): Table
    {
        $t = new Table('_log');
        $t->addColumn('id', Types::BIGINT, ['unsigned' => true, 'autoincrement' => true]);
        $t->addColumn('created_at', Types::DATETIME_MUTABLE);
        $t->addColumn('level_name', Types::ENUM, ['notnull' => false, 'values' => ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency']]);
        $t->addColumn('message', Types::STRING, ['length' => 1024, 'default' => '']);
        $t->addColumn('context', Types::TEXT, ['length' => 65535, 'notnull' => false]);
        $t->addColumn('channel', Types::STRING, ['length' => 255, 'default' => '']);
        $t->addColumn('cmd', Types::STRING, ['length' => 255, 'notnull' => false]);
        $t->addColumn('method', Types::STRING, ['length' => 255, 'notnull' => false]);
        $t->addColumn('uid', Types::STRING, ['length' => 32, 'notnull' => false]);
        $t->addColumn('count', Types::INTEGER, ['notnull' => false]);
        $t->addColumn('time', Types::SMALLFLOAT, ['notnull' => false]);
        $t->addColumn('level', Types::SMALLINT, ['default' => 0]);
        $t->addColumn('exception_class', Types::STRING, ['length' => 1024, 'notnull' => false]);
        $t->addColumn('exception_message', Types::STRING, ['length' => 1024, 'notnull' => false]);
        $t->addColumn('exception_line', Types::STRING, ['length' => 1024, 'notnull' => false]);
        $t->addColumn('exception_trace', Types::TEXT, ['length' => 65535, 'notnull' => false]);
        $t->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create());
        $t->addIndex(['created_at', 'level'], 'log_created_at_level');

        return $t;
    }
}
