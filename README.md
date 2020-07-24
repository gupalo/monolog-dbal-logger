Monolog Handler for DBAL
========================

Installation
------------

Add to your project via composer:

    composer require gupalo/monolog-dbal-logger

Create log table.

    CREATE TABLE `_log` (
        `id`                    bigint UNSIGNED NOT NULL AUTO_INCREMENT,
        `created_at`            datetime NOT NULL,
        `level`                 smallint NOT NULL DEFAULT 0,
        `level_name`            enum('debug','info','notice','warning','error','critical','alert','emergency') NULL DEFAULT NULL,
        `channel`               varchar(255) NOT NULL DEFAULT '',
        `message`               varchar(1024) NOT NULL DEFAULT '',
        `context`               text NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        INDEX `log_created_at_level` (`created_at`, `level`) USING BTREE
    ) DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_general_ci;


Example
-------

`$connection` is `Doctrine\DBAL\Connection` 

    $handler = new \Gupalo\MonologDbalLogger\MonologDbalLogger($connection);
    $logger = new \Monolog\Logger();
    $logger->pushHandler($handler);
    
    $logger->addWarning('You might not read the docs', ['page' => 17, 'username' => 'guest']);

Symfony
-------

Configuration example:

`services.yaml`

    Gupalo\MonologDbalLogger\Symfony\ErrorLogListener:
        tags: [ {name: 'kernel.event_subscriber'} ]

    monolog.dbal_handler:
        class: 'Gupalo\MonologDbalLogger\MonologDbalLogger'
        public: true
        bind:
            $connection: '@doctrine.dbal.default_connection'
            $level: 200
        tags: ['monolog.logger']

`monolog.yaml`

    monolog:
        handlers:
            db:
                type: service
                id: 'monolog.dbal_handler' # note - no "@"
                channels: ["!event", "!request", "!security"]

Configure
---------

Params in `MonologDbalLogger::__construct`:

* `string $table = '_log'`: table name 
* `int $maxRows = 100000`: if you have more rows than `$maxRows` then cleaner will eventually (1 in 1000 chances) remove them

Extend
------

You may extend `MonologDbalLogger` and add your own logic. See `MyMonologDbalLogger` as an example.
If you add additional fields, don't forget to change table creation SQL.

Table:

    CREATE TABLE `_log` (
        `id`                    bigint UNSIGNED NOT NULL AUTO_INCREMENT,
        `created_at`            datetime NOT NULL,
        `level`                 smallint NOT NULL DEFAULT 0,
        `level_name`            enum('debug','info','notice','warning','error','critical','alert','emergency') NULL DEFAULT NULL,
        `channel`               varchar(255) NOT NULL DEFAULT '',
        `message`               varchar(1024) NOT NULL DEFAULT '',
        `cmd`                   varchar(255) NULL DEFAULT NULL,
        `method`                varchar(255) NULL DEFAULT NULL,
        `uid`                   varchar(32) NULL DEFAULT NULL,
        `count`                 int NULL DEFAULT NULL,
        `time`                  float NULL DEFAULT NULL,
        `context`               text NULL DEFAULT NULL,
        `exception_class`       varchar(1024) NULL DEFAULT NULL,
        `exception_message`     varchar(1024) NULL DEFAULT NULL,
        `exception_line`        varchar(1024) NULL DEFAULT NULL,
        `exception_trace`       text NULL,
        PRIMARY KEY (`id`),
        INDEX `log_created_at_level` (`created_at`, `level`) USING BTREE
    ) DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_general_ci;

Other
-----

See `tests`.
