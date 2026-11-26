# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.12.1] - 2026-11-26

### Fixed

- The `Log` entity now maps the `_log` table from the README exactly, so
  `doctrine:schema:update --dump-sql` prints nothing for `_log`. No ALTER is needed on an existing
  table. Before this release, a schema update would have rewritten the table. In detail:
  - `id` is `BIGINT UNSIGNED`, `level` is `SMALLINT`, and `level_name` is an ENUM of the eight
    Monolog level names.
  - `time` is a single-precision `FLOAT`, not `DOUBLE PRECISION`.
  - `cmd`, `method`, `uid` (now `VARCHAR(32)`) and all `exception_*` columns are nullable.
  - The `(created_at, level)` index is named `log_created_at_level`.
- `context` is mapped as `TEXT`, not JSON. The logger truncates the context, so a stored value is
  not always valid JSON, and reading such a row used to fail. `getContext()` now decodes the text
  and returns `['raw' => <stored text>]` when the text is not valid JSON.

### Added

- `Log::getContextRaw()` returns the stored context text as it is.

### Changed

- A new `Log` object now defaults its nullable fields (`levelName`, `cmd`, `method`, `uid`,
  `exception*`) to `null` instead of `''`.

## [1.12.0] - 2026-11-18

### Added

- `ErrorLogListener::filterRequestParams(array $params): array`, an overridable hook to hash or drop
  sensitive query and body parameters (passwords, tokens) before an HTTP exception is logged.
  It is the last point where the parameters are still an array.

### Security

- If the filter changes the query, `url` is logged without its query string, so the unfiltered
  values do not leak through the URL. If the filter throws, `query` and `request` are logged as
  `[filter failed]` and `url` is logged without its query string.

## [1.11.3] - 2026-11-17

### Security

- `ErrorLogListener` request masking now also masks array keys and the output of
  `JsonSerializable` objects. Other objects are logged as `[ClassName]`.
- Masking work is capped at 10,000 bytes of input in total, and nesting deeper than 64 levels is
  logged as `[too deep]`.
- If a masking regex fails (for example, on the PCRE backtrack limit), the value is logged as
  `[mask failed]` instead of unmasked, and the logger does not throw.

## [1.11.2] - 2026-11-17

### Fixed

- `ErrorLogListener` no longer raises "Array to string conversion" from inside the exception logger
  when request parameters contain invalid UTF-8 or are nested too deeply. Invalid bytes are
  substituted. A value that still can't be encoded is logged as `[unencodable <type>: <error>]`.
  Exceptions thrown by `jsonSerialize()` are caught.

### Security

- Credentials in URLs and e-mail addresses are now masked in each value before JSON encoding.
  Before, a password containing a quote could escape the mask because of JSON escaping.

## [1.11.1] - 2026-11-17

### Changed

- `ErrorLogListener` logs HTTP exceptions with a 4xx status (404 from scanners, 405, 429, …) at
  `warning` instead of `error`, so they stay in the log without reaching error trackers such as
  Sentry. `ErrorLogListener::logException()` takes an optional `$level` argument.

## [1.11.0] - 2026-04-11

Maintenance release: the library code is unchanged, and the test suite moved to PHPUnit 13.

## [1.10.0] - 2026-04-10

### Added

- Age-based retention in `MonologDbalCleaner`, with three new constructor arguments:
  `$infoRetentionDays = 1` (level < 250), `$warningRetentionDays = 7` (250 ≤ level < 400) and
  `$errorRetentionDays = 30` (level ≥ 400). Set any of them to `0` to turn that rule off.

### Changed

- **Behaviour change:** with the defaults, the cleaner now deletes info and debug rows older than
  1 day, warnings older than 7 days and errors older than 30 days.
- The `$maxRows` limit now trims only rows below `warning` (level < 250). Warnings and errors are
  removed only by age.
- Cleanup runs less often: after a run is due, it also has a 1-in-101 random chance.

## [1.9.0] - 2026-01-09

### Changed

- **Breaking:** requires PHP 8.4+, `doctrine/dbal` ^4.4, `monolog/monolog` ^3.10 and Symfony
  ^7.4 or ^8.0. Symfony 8 is now supported.

## [1.8.1] - 2025-07-01

### Changed

- **Breaking:** more EasyAdmin and entity helper classes are renamed so they don't clash with an
  application's own classes. `YamlField` is now `DbalLoggerYamlField`, `YamlType` is now
  `DbalLoggerYamlType`, and `VirtualFieldsEntityTrait` is now `DbalLoggerVirtualFieldsEntityTrait`.

## [1.8.0] - 2025-07-01

### Changed

- **Breaking:** EasyAdmin helper classes are renamed so they don't clash with an application's own
  classes. `Helpers\CrudField` is now `Helpers\DbalLoggerCrudField`, `Traits\CrudControllerTrait`
  is now `Traits\DbalLoggerCrudControllerTrait`, and `Traits\ReadOnlyCrudControllerTrait` is now
  `Traits\DbalLoggerReadOnlyCrudControllerTrait`.

## [1.7.5] - 2025-05-25

### Changed

- The read-only log CRUD shows the edit (detail) action on the index page, so you can open a log
  row from the list.

## [1.7.4] - 2025-01-12

### Fixed

- PHP 8.4 compatibility: `ErrorLogListener::logException()` and `formatExceptionContext()` declare
  `$context` as explicitly nullable.

## [1.7.3] - 2025-01-12

### Fixed

- PHP 8.4 compatibility: the `$cleaner` argument of `MonologDbalLogger::__construct()` is explicitly
  nullable.

## [1.7.2] - 2025-01-12

### Changed

- Reverts the 1.7.1 rename: the controller is `LogCrudController` again.

## [1.7.1] - 2025-01-12

### Changed

- `LogCrudController` is renamed to `BaseLogCrudController`. This was reverted in 1.7.2.

## [1.7.0] - 2025-01-12

### Removed

- The EasyAdmin pretty-URL templates (`@EasyAdminPrettyUrls/...`) are no longer applied by the
  CRUD trait, so `marcin-jozwikowski/easy-admin-pretty-urls` is no longer needed. Pretty URLs are
  deprecated in EasyAdmin.

## [1.6.3] - 2024-12-02

### Added

- The log CRUD can be filtered by `id` (for example, `id >= {current max id}`).

## [1.6.2] - 2024-12-01

### Changed

- Documentation only: the README explains how to register `LogRepository` as a Doctrine repository
  service.

## [1.6.1] - 2024-12-01

### Changed

- `LogCrudController` no longer carries the pretty-URLs route attribute. The README shows how to map
  the `Gupalo` entity namespace in Doctrine and how to extend the controller.

## [1.6.0] - 2024-12-01

### Added

- An EasyAdmin log viewer: a `Log` Doctrine entity and `LogRepository` for the `_log` table, and a
  read-only `LogCrudController` with level badges, a YAML context view, and filters on message,
  level, context, channel, command, exception fields and date. It needs `doctrine/orm`,
  `easycorp/easyadmin-bundle` and `gupalo/symfony-form-transformers` in the application.

### Changed

- `MonologDbalCleaner` runs at most once an hour, with a 1-in-1000 chance on each log write. Before,
  it ran when the Unix time was a multiple of 1000, at most once a minute.

## [1.5.0] - 2024-07-26

### Added

- Support for `doctrine/dbal` 4.

## [1.4.3] - 2026-11-17

Backport to the 1.4 line (PHP 8.1, DBAL 3, Symfony 5.4–7), released after 1.12.0.

### Fixed

- `ErrorLogListener` request logging gets the fixes from 1.11.2 and 1.11.3. It no longer casts
  arrays to string, it tolerates invalid UTF-8 and deep nesting, it masks keys, values and
  `JsonSerializable` output one by one, it caps masking work at 10,000 bytes, and a PCRE failure
  never logs a value unmasked or throws.

## [1.4.2] - 2024-01-26

### Changed

- **Breaking for subclasses:** the handler uses the Monolog 3 and DBAL 3 APIs. `write()` takes a
  `Monolog\LogRecord`, `$this->record` is a `LogRecord`, and the default level is
  `Monolog\Level::Debug`. Insert failures of any kind are now swallowed.

## [1.4.1] - 2024-01-26

### Changed

- **Breaking:** requires `monolog/monolog` ^3.5 and `doctrine/dbal` ^3.8. Monolog 2 and DBAL 2
  are dropped. The handler code was ported to the Monolog 3 API only in 1.4.2, so use 1.4.2
  instead of this release.

## [1.4.0] - 2024-01-26

### Changed

- **Breaking:** requires PHP 8.1+. Symfony 7 is now supported.

## [1.3.1] - 2022-01-23

### Fixed

- The console command start time is stored as a float, so the `time` logged at `console.end` keeps
  sub-second precision.

### Changed

- Requires Symfony ^5.4 or ^6.0.

## [1.3.0] - 2022-01-06

### Added

- Support for `doctrine/dbal` 3.2+ and Symfony 6.

## [1.2.0] - 2020-09-30

### Changed

- The example `MyMonologDbalLogger` skips the Doctrine Inflector `classify` deprecation message.

## [1.1.4] - 2020-07-24

### Changed

- `console.begin` logs the command `input` (options and arguments) as an array, not as a JSON
  string.

## [1.1.3] - 2020-07-24

### Changed

- Request data in exception logs is encoded as compact JSON instead of pretty-printed.

## [1.1.2] - 2020-07-24

### Fixed

- A console exception no longer resets command timing, so `console.end` still reports the command
  `time`.

## [1.1.1] - 2020-07-24

### Fixed

- `MyMonologDbalLogger` reads the exception from the record context. Each step that extracts
  fields is wrapped, so one failing step no longer drops the log record.

## [1.1.0] - 2020-07-24

### Added

- `Symfony\ErrorLogListener`, an event subscriber that logs HTTP exceptions (with IP, URL, query
  and body, and masks URL credentials and e-mail addresses), logs console command
  begin/end/error/exception with timing, and registers Monolog's `ErrorHandler`. It requires
  `symfony/console`, `symfony/event-dispatcher` and `symfony/http-kernel` ^5.1.
- `MyMonologDbalLogger` takes `exception_*` fields passed directly in the context.

## [1.0.6] - 2020-07-23

### Fixed

- `MyMonologDbalLogger` removes the exception object from the stored context after it extracts the
  exception fields.

## [1.0.5] - 2020-07-23

### Fixed

- `MyMonologDbalLogger` writes its additional columns (`cmd`, `method`, `uid`, `count`, `time`,
  `exception_*`) from the extracted fields, not from the raw context.

## [1.0.4] - 2020-07-23

### Added

- An overridable `needSkip()` in `MyMonologDbalLogger`, which skips `E_USER_DEPRECATED` errors.

## [1.0.3] - 2020-07-23

### Fixed

- `MyMonologDbalLogger` no longer emits undefined-index notices for missing context keys, and the
  `cmd` message detection works.

## [1.0.2] - 2020-07-23

### Changed

- `MonologDbalCleaner` waits at least 60 seconds between runs. The README documents the `$table`
  and `$maxRows` constructor arguments.

## [1.0.1] - 2020-07-23

### Changed

- The base `MonologDbalLogger` no longer skips `E_USER_DEPRECATED` errors.

### Fixed

- The README service id has no `@`.
- `MyMonologDbalLogger` takes `cmd` from the context `name`.

## [1.0.0] - 2020-07-23

### Added

- First release. `MonologDbalLogger` is a Monolog handler that writes records to a database table
  (`_log` by default) through Doctrine DBAL. `MonologDbalCleaner` trims the table to `$maxRows`
  rows. `MyMonologDbalLogger` is an example subclass with extra columns. Requires PHP 7.4+,
  `doctrine/dbal` ^2.10 and `monolog/monolog` ^2.1.

[Unreleased]: https://github.com/gupalo/monolog-dbal-logger/compare/1.12.1...HEAD
[1.12.1]: https://github.com/gupalo/monolog-dbal-logger/compare/1.12.0...1.12.1
[1.12.0]: https://github.com/gupalo/monolog-dbal-logger/compare/1.11.3...1.12.0
[1.11.3]: https://github.com/gupalo/monolog-dbal-logger/compare/1.11.2...1.11.3
[1.11.2]: https://github.com/gupalo/monolog-dbal-logger/compare/1.11.1...1.11.2
[1.11.1]: https://github.com/gupalo/monolog-dbal-logger/compare/1.11.0...1.11.1
[1.11.0]: https://github.com/gupalo/monolog-dbal-logger/compare/1.10.0...1.11.0
[1.10.0]: https://github.com/gupalo/monolog-dbal-logger/compare/1.9.0...1.10.0
[1.9.0]: https://github.com/gupalo/monolog-dbal-logger/compare/1.8.1...1.9.0
[1.8.1]: https://github.com/gupalo/monolog-dbal-logger/compare/1.8.0...1.8.1
[1.8.0]: https://github.com/gupalo/monolog-dbal-logger/compare/1.7.5...1.8.0
[1.7.5]: https://github.com/gupalo/monolog-dbal-logger/compare/1.7.4...1.7.5
[1.7.4]: https://github.com/gupalo/monolog-dbal-logger/compare/1.7.3...1.7.4
[1.7.3]: https://github.com/gupalo/monolog-dbal-logger/compare/1.7.2...1.7.3
[1.7.2]: https://github.com/gupalo/monolog-dbal-logger/compare/1.7.1...1.7.2
[1.7.1]: https://github.com/gupalo/monolog-dbal-logger/compare/1.7.0...1.7.1
[1.7.0]: https://github.com/gupalo/monolog-dbal-logger/compare/1.6.3...1.7.0
[1.6.3]: https://github.com/gupalo/monolog-dbal-logger/compare/1.6.2...1.6.3
[1.6.2]: https://github.com/gupalo/monolog-dbal-logger/compare/1.6.1...1.6.2
[1.6.1]: https://github.com/gupalo/monolog-dbal-logger/compare/1.6.0...1.6.1
[1.6.0]: https://github.com/gupalo/monolog-dbal-logger/compare/1.5.0...1.6.0
[1.5.0]: https://github.com/gupalo/monolog-dbal-logger/compare/1.4.2...1.5.0
[1.4.3]: https://github.com/gupalo/monolog-dbal-logger/compare/1.4.2...1.4.3
[1.4.2]: https://github.com/gupalo/monolog-dbal-logger/compare/1.4.1...1.4.2
[1.4.1]: https://github.com/gupalo/monolog-dbal-logger/compare/1.4.0...1.4.1
[1.4.0]: https://github.com/gupalo/monolog-dbal-logger/compare/1.3.1...1.4.0
[1.3.1]: https://github.com/gupalo/monolog-dbal-logger/compare/1.3.0...1.3.1
[1.3.0]: https://github.com/gupalo/monolog-dbal-logger/compare/1.2.0...1.3.0
[1.2.0]: https://github.com/gupalo/monolog-dbal-logger/compare/1.1.4...1.2.0
[1.1.4]: https://github.com/gupalo/monolog-dbal-logger/compare/1.1.3...1.1.4
[1.1.3]: https://github.com/gupalo/monolog-dbal-logger/compare/1.1.2...1.1.3
[1.1.2]: https://github.com/gupalo/monolog-dbal-logger/compare/1.1.1...1.1.2
[1.1.1]: https://github.com/gupalo/monolog-dbal-logger/compare/1.1.0...1.1.1
[1.1.0]: https://github.com/gupalo/monolog-dbal-logger/compare/1.0.6...1.1.0
[1.0.6]: https://github.com/gupalo/monolog-dbal-logger/compare/1.0.5...1.0.6
[1.0.5]: https://github.com/gupalo/monolog-dbal-logger/compare/1.0.4...1.0.5
[1.0.4]: https://github.com/gupalo/monolog-dbal-logger/compare/1.0.3...1.0.4
[1.0.3]: https://github.com/gupalo/monolog-dbal-logger/compare/1.0.2...1.0.3
[1.0.2]: https://github.com/gupalo/monolog-dbal-logger/compare/1.0.1...1.0.2
[1.0.1]: https://github.com/gupalo/monolog-dbal-logger/compare/1.0.0...1.0.1
[1.0.0]: https://github.com/gupalo/monolog-dbal-logger/releases/tag/1.0.0
