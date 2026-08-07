# Laravel DataDog Logger

A custom Laravel Monolog logger for sending logs to DataDog via their HTTP API.

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

## Features

- Send Laravel logs directly to DataDog via HTTP API
- Automatic exception tracking with stack traces
- Custom log context support
- Configurable log levels and endpoints
- Error reporting for failed API requests
- Environment-based tagging

## Requirements

- PHP ^8.1 || ^8.2 || ^8.3 || ^8.4
- Laravel 10.x or higher
- ext-json
- ext-curl
- Guzzle ^6.0 || ^7.0
- Monolog ^3.0

## Installation

Install the package via Composer:

```bash
composer require shadowbane/laravel-datadog-logger
```

The service provider will be automatically registered.

## Configuration

### Environment Variables

Add the following to your `.env` file:

```env
DATADOG_API_KEY=your-datadog-api-key
DATADOG_LEVEL=warning
DATADOG_BUBBLE=true
DATADOG_ENVIRONMENT=production
DATADOG_API_ENDPOINT=https://http-intake.logs.datadoghq.com/api/v2/logs
DATADOG_ERROR_LOG_CHANNEL=stack
```

#### Configuration Options

| Variable | Description | Default |
|----------|-------------|---------|
| `DATADOG_API_KEY` | Your DataDog API key (required) | - |
| `DATADOG_LEVEL` | Minimum log level (debug, info, notice, warning, error, critical, alert, emergency) | `warning` |
| `DATADOG_BUBBLE` | Whether logs should bubble to other handlers | `true` |
| `DATADOG_ENVIRONMENT` | Environment tag for DataDog | Laravel's `app()->environment()` |
| `DATADOG_API_ENDPOINT` | DataDog logs API endpoint | `https://http-intake.logs.datadoghq.com/api/v2/logs` |
| `DATADOG_ERROR_LOG_CHANNEL` | Laravel log channel for logging DataDog API failures | `false` |

### Laravel Logging Configuration

The package automatically merges the `datadog-api` channel into your logging configuration. You can use it in your `config/logging.php`:

```php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['single', 'datadog-api'],
    ],
]
```

## Usage

### Basic Logging

Use the `datadog-api` channel to send logs to DataDog:

```php
use Illuminate\Support\Facades\Log;

Log::channel('datadog-api')->info('User logged in', [
    'user_id' => 123,
    'ip_address' => '192.168.1.1',
]);

Log::channel('datadog-api')->error('Payment failed', [
    'order_id' => 456,
    'amount' => 99.99,
]);
```

### Exception Logging

When logging exceptions, pass the exception in the context array with the key `exception`:

```php
try {
    // Your code
} catch (\Exception $e) {
    Log::channel('datadog-api')->error('Failed to create company: ' . $e->getMessage(), [
        'exception' => $e,
        'company_id' => 123,
        'user_id' => 456,
    ]);
}
```

The logger will automatically extract:
- Exception message (becomes the message; falls back to the class name when the exception carries no message)
- Exception class (`error.kind`) — group by `@error.kind` in DataDog
- Error message (`error.message`)
- Stack trace (`error.stack`), including the full `getPrevious()` chain
- Source location (`error.file`, `error.line`)

Anything implementing `Throwable` is handled, so PHP's `Error` hierarchy
(`TypeError`, `ValueError`, `ArgumentCountError`) is captured alongside `Exception`.

Wrapped exceptions are rendered in full, separated by `Caused by:`. This matters
because the outermost exception is often a generic wrapper, while the detail that
identifies the failure lives in the cause:

```
RuntimeException: Unable to complete request in /app/src/Client.php:88
#0 …

Caused by: PDOException: SQLSTATE[HY000] [2002] Connection refused in /app/src/Db.php:31
#0 …
```

### Log Context

All context data (except `exception`, `message`, and `messages`) is sent to DataDog as custom attributes:

```php
Log::channel('datadog-api')->warning('High memory usage', [
    'memory_used' => '512MB',
    'memory_limit' => '256MB',
    'server' => 'web-01',
]);
```

This will create a log in DataDog with custom attributes: `memory_used`, `memory_limit`, and `server`.

### Testing

Test your DataDog integration using the included Artisan command:

```bash
php artisan datadog:send-test-exception
```

This command sends a test exception to DataDog with sample context data.

## Log Level Mapping

Laravel log levels are mapped to DataDog status levels:

| Laravel Level | DataDog Status |
|---------------|----------------|
| debug, info | info |
| notice, warning | warn |
| error, critical, alert, emergency | error |

## Error Handling

If the DataDog API request fails, you can configure a fallback Laravel log channel to capture these errors:

```env
DATADOG_ERROR_LOG_CHANNEL=stack
```

Failed API requests will be logged to this channel with the Guzzle exception details.

## DataDog Log Structure

Logs sent to DataDog include the following fields:

- `ddsource`: Always set to `laravel`
- `ddtags`: Environment tag (e.g., `env:production`)
- `hostname`: Server hostname
- `message`: Formatted log message (or the exception message when one is present)
- `service`: Your Laravel application name (from `config('app.name')`)
- `status`: Mapped log level (info, warn, error)
- `timestamp`: Log timestamp in milliseconds
- Custom attributes from log context

For throwables:
- `error.kind`: Exception class
- `error.message`: Exception message
- `error.stack`: Stack trace, including the `Caused by:` chain
- `error.file` / `error.line`: Where it was thrown

## Development

### Static Analysis

Run PHPStan for static analysis:

```bash
vendor/bin/phpstan analyze
```

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## Credits

- [Shadowbane](https://github.com/shadowbane)

## Support

If you discover any issues, please open an issue on the [GitHub repository](https://github.com/shadowbane/laravel-datadog-logger/issues).