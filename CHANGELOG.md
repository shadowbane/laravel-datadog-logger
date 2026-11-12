# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.0.0] - 2026-11-12

### Added

- Logs are buffered and sent to DataDog in batches instead of one blocking HTTP request per log line. On php-fpm the batch is sent after the response has reached the client.
- Buffered logs are flushed after every queued job (`JobProcessed`, `JobExceptionOccurred`, `JobFailed`) and when the application terminates, so long-running queue workers do not hold logs until they exit.
- `DATADOG_BUFFER` option (default `true`). Set it to `false` to send each log synchronously, as in earlier versions.
- `DATADOG_TIMEOUT` option (default `2` seconds) for the connect and total timeout of the DataDog request.
- `logger.message` attribute on logs that carry an exception, holding the message the application passed to the logger when it differs from the exception message.

### Changed

- **Breaking:** buffering is on by default. Logs still in memory when a process is killed (`SIGKILL`, out-of-memory) are lost; set `DATADOG_BUFFER=false` to restore the previous synchronous behaviour.
- Batches are split to respect DataDog's limits of 1000 entries and 5MB per request.

### Fixed

- `timestamp` is taken from the time the record was logged, not the time it was sent.
- A record containing invalid UTF-8 no longer fails the whole request; invalid bytes are replaced with `U+FFFD`.
- The DataDog request no longer waits indefinitely when the API does not respond.

## [1.3.0] - 2026-11-12

### Added

- Support for Guzzle 8.
- Support for Laravel 11, 12 and 13.

### Changed

- Declared the `illuminate/support` and `illuminate/console` dependencies (`^10.0` to `^13.0`) that the package already relied on.
- `CustomizeFormatter` reads handlers from the underlying Monolog logger and skips handlers that do not accept a formatter.

### Fixed

- `TestException` rejected a previous exception with a `TypeError`, because `Throwable` resolved to a class in the package namespace (regression in 1.2.0).

## [1.2.1] - 2026-08-07

### Added

- `error.stack` includes the full chain of previous exceptions, separated by `Caused by:`.

### Changed

- `error.kind` is the exception class instead of the exception code.
- `message` is the exception message, falling back to the class name when the message is empty, instead of always the class name.

### Fixed

- PHP `Error` throwables (`TypeError`, `ValueError`, …) are reported with `error.*` attributes; previously only `Exception` subclasses were.

## [1.2.0] - 2025-10-07

### Added

- Log context is sent as custom DataDog attributes. Nested arrays are flattened into dotted keys; the `exception`, `message` and `messages` keys are left out.
- `error.file` and `error.line` attributes for exceptions.
- Support for PHP 8.4.
- README.

### Fixed

- Deprecation for an implicitly nullable parameter in `TestException`.

## [1.1.0] - 2024-11-08

### Added

- `DATADOG_API_ENDPOINT` option to send logs to another DataDog site.
- `DATADOG_ERROR_LOG_CHANNEL` option to log failed DataDog requests to another Laravel log channel.
- `timestamp` attribute on every log.
- Support for PHP 8.3.

### Changed

- Requests ask for a gzip-compressed response.
- `php artisan datadog:send-test-exception` logs the test exception to the `datadog-api` channel and prints it, instead of throwing it.

## [1.0.0] - 2023-05-29

### Added

- Support for Laravel 10.

### Changed

- **Breaking:** requires Monolog 3 and PHP 8.1 or later. The handler uses Monolog 3's `LogRecord` and `Level` types.

### Removed

- **Breaking:** support for PHP 7.4 and 8.0.

## [0.2.3] - 2022-08-03

### Added

- `DATADOG_ENVIRONMENT` option to override the `env` tag, which defaults to the Laravel environment.

## [0.2.2] - 2022-07-26

### Added

- `php artisan datadog:send-test-exception` command to send a test exception.

## [0.2.1] - 2022-07-19

### Changed

- The handler defaults to the `warning` level when no level is configured.

### Removed

- The unused `region` option.

## [0.2.0] - 2022-07-19

### Changed

- Log messages are formatted by `CustomizeFormatter` as `channel.LEVEL: message` followed by context and extra.
- The default `DATADOG_LEVEL` is `warning` instead of `info`.

### Removed

- `DataDogFormatter`.

## [0.1.1] - 2022-07-19

### Added

- Laravel package auto-discovery for the service provider.

## [0.1.0] - 2022-07-19

### Added

- Initial release: a `datadog-api` log channel that sends Laravel logs to the DataDog HTTP intake API.

[Unreleased]: https://github.com/shadowbane/laravel-datadog-logger/compare/v2.0.0...HEAD
[2.0.0]: https://github.com/shadowbane/laravel-datadog-logger/compare/v1.3.0...v2.0.0
[1.3.0]: https://github.com/shadowbane/laravel-datadog-logger/compare/v1.2.1...v1.3.0
[1.2.1]: https://github.com/shadowbane/laravel-datadog-logger/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/shadowbane/laravel-datadog-logger/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/shadowbane/laravel-datadog-logger/compare/v1.0...v1.1.0
[1.0.0]: https://github.com/shadowbane/laravel-datadog-logger/compare/v0.2.3...v1.0
[0.2.3]: https://github.com/shadowbane/laravel-datadog-logger/compare/v0.2.2...v0.2.3
[0.2.2]: https://github.com/shadowbane/laravel-datadog-logger/compare/v0.2.1...v0.2.2
[0.2.1]: https://github.com/shadowbane/laravel-datadog-logger/compare/v0.2...v0.2.1
[0.2.0]: https://github.com/shadowbane/laravel-datadog-logger/compare/v0.1.1...v0.2
[0.1.1]: https://github.com/shadowbane/laravel-datadog-logger/compare/v0.1...v0.1.1
[0.1.0]: https://github.com/shadowbane/laravel-datadog-logger/releases/tag/v0.1
