# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

A Monolog handler that writes log entries to a database via Doctrine DBAL. Includes Symfony integration (event subscriber for HTTP/console events) and EasyAdmin CRUD controller for viewing logs.

## Commands

```bash
# Run tests
vendor/bin/phpunit

# Run single test
vendor/bin/phpunit tests/MonologDbalCleanerTest.php

# Static analysis
vendor/bin/phpstan analyse
```

## Architecture

### Core Components

- **MonologDbalLogger** (`src/MonologDbalLogger.php`) - Main handler extending Monolog's `AbstractProcessingHandler`. Override `getAdditionalData()` and `initContextAndAdditionalFields()` to add custom fields.

- **MyMonologDbalLogger** - Example extension showing how to add exception details, command info, and additional fields (uid, count, time). Demonstrates `needSkip()` for filtering logs.

- **MonologDbalCleaner** - Auto-cleanup of old log entries. Runs probabilistically (1/1000 chance when >1 hour since last cleanup) to avoid performance impact. Keeps `maxRows` most recent entries.

### Symfony Integration

- **ErrorLogListener** (`src/Symfony/`) - Event subscriber that logs HTTP exceptions, console command lifecycle (begin/end/error), and registers Monolog's ErrorHandler.

### EasyAdmin Integration

- **LogCrudController** (`src/EasyAdmin/Controller/`) - Read-only CRUD controller for viewing logs. Uses traits for configuration and read-only behavior.
- **Log Entity** (`src/Entity/Log.php`) - Doctrine ORM entity mapped to `_log` table.

## Database

Default table name is `_log`. Basic schema has: id, created_at, level, level_name, channel, message, context. Extended schema (MyMonologDbalLogger) adds: method, cmd, uid, count, time, exception_class, exception_message, exception_line, exception_trace.

## Extending

1. Extend `MonologDbalLogger`
2. Override `getAdditionalData()` to return additional column data
3. Override `initContextAndAdditionalFields()` to extract fields from context
4. Override `needSkip()` to filter unwanted log entries
5. Update SQL schema to include new columns
