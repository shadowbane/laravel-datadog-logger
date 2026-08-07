<?php

namespace Shadowbane\DatadogLogger\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Handler\MissingExtensionException;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;

/**
 * Class DataDogApiHandler.
 *
 * @extends AbstractProcessingHandler
 */
class DataDogApiHandler extends AbstractProcessingHandler
{
    /** @var string */
    protected string $token;

    /**
     * @param  string  $token  API token supplied by DataDog
     * @param  string|int  $level  The minimum logging level to trigger this handler
     * @param  bool  $bubble  whether or not messages that are handled should bubble up the stack
     *
     * @throws MissingExtensionException If the curl extension is missing
     */
    public function __construct(string $token, $level = Logger::WARNING, bool $bubble = true)
    {
        if (! extension_loaded('curl')) {
            throw new MissingExtensionException('The curl extension is needed to use the DataDogApiHandler');
        }
        $this->token = $token;
        parent::__construct($level, $bubble);
    }

    /**
     * Get endpoint used to send the logs.
     *
     * @return string
     */
    protected function getEndpoint(): string
    {
        return config('logging.channels.datadog-api.endpoint', 'https://http-intake.logs.datadoghq.com/api/v2/logs');
    }

    /**
     * Write implementation of AbstractProcessingHandler.
     *
     * @param  LogRecord  $record
     * @return void
     */
    protected function write(LogRecord $record): void
    {
        $this->send($record);
    }

    /**
     * Send the log.
     *
     * @param  LogRecord  $record
     * @return void
     */
    protected function send(LogRecord $record): void
    {
        try {
            $client = new Client;
            $client->request(
                'POST',
                $this->getEndpoint(),
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'DD-API-KEY' => $this->token,
                        'Accept-Encoding' => 'gzip',
                    ],
                    'body' => json_encode($this->createBody($record)),
                ]
            );
        } catch (GuzzleException $e) {
            $errLoggingChannel = config('logging.channels.datadog-api.error');
            if ($errLoggingChannel) {
                Log::channel($errLoggingChannel)->critical($e->getMessage(), [
                    'exception' => $e,
                ]);
            }

            return;
        }
    }

    /**
     * Create the body of the log to send
     * to DataDog via the API.
     *
     * @param  LogRecord  $record
     * @return array
     */
    private function createBody(LogRecord $record): array
    {
        $body = [
            'ddsource' => 'laravel',
            'ddtags' => $this->getTags(),
            'hostname' => gethostname(),
            'message' => $record->formatted,
            'service' => config('app.name'),
            'status' => $this->getLogStatus($record->level),
            'timestamp' => now()->getPreciseTimestamp(3),
        ];

        $throwable = $record->context['exception'] ?? null;

        // \Throwable, not \Exception: PHP's Error hierarchy (TypeError, ValueError,
        // ArgumentCountError) does not extend Exception, so fatals used to reach
        // DataDog with no error.* attributes at all.
        if ($throwable instanceof \Throwable) {
            // error.kind is DataDog's error TYPE, which is the class — getCode() is
            // usually 0 and never groups usefully.
            $body['error.kind'] = get_class($throwable);
            $body['error.message'] = $throwable->getMessage();
            $body['error.stack'] = $this->formatThrowable($throwable);
            $body['error.file'] = $throwable->getFile();
            $body['error.line'] = $throwable->getLine();

            // Keep a readable body. Grouping by exception class is what
            // `@error.kind` is for, so the class name no longer displaces the
            // message here.
            $body['message'] = $throwable->getMessage() !== ''
                ? $throwable->getMessage()
                : get_class($throwable);
        }

        // Add log context as custom attributes, excluding specific keys
        $context = $this->filterContext($record->context);
        if (! empty($context)) {
            $body = array_merge($body, $context);
        }

        return $body;
    }

    /**
     * Render a throwable and every throwable it wraps.
     *
     * getTraceAsString() describes only the outermost throwable, so when an
     * exception wraps another the cause never reaches DataDog. The whole chain is
     * rendered, in full — deciding which frames matter is the reader's job, not
     * this handler's.
     *
     * @param  \Throwable  $throwable
     * @return string
     */
    private function formatThrowable(\Throwable $throwable): string
    {
        $parts = [];

        // Termination guard only. A `previous` cycle cannot occur through normal
        // construction, and this drops nothing that a linear chain would report.
        $seen = new \SplObjectStorage;

        do {
            $seen->attach($throwable);

            $parts[] = sprintf(
                "%s: %s in %s:%d\n%s",
                get_class($throwable),
                $throwable->getMessage(),
                $throwable->getFile(),
                $throwable->getLine(),
                $throwable->getTraceAsString()
            );

            $throwable = $throwable->getPrevious();
        } while ($throwable !== null && ! $seen->contains($throwable));

        return implode("\n\nCaused by: ", $parts);
    }

    /**
     * Filter log context to exclude specific keys that are handled separately.
     * Flattens nested arrays using dot notation for DataDog compatibility.
     *
     * @param  array  $context
     * @return array
     */
    private function filterContext(array $context): array
    {
        $excludedKeys = ['exception', 'message', 'messages'];

        $filtered = array_filter(
            $context,
            fn($key) => ! in_array($key, $excludedKeys),
            ARRAY_FILTER_USE_KEY
        );

        return $this->flattenArray($filtered);
    }

    /**
     * Flatten a nested array using dot notation.
     *
     * @param  array  $array
     * @param  string  $prefix
     * @return array
     */
    private function flattenArray(array $array, string $prefix = ''): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $newKey = $prefix === '' ? $key : $prefix.'.'.$key;

            if (is_array($value) && ! empty($value)) {
                $result = array_merge($result, $this->flattenArray($value, $newKey));
            } elseif (is_object($value)) {
                // Convert objects to string representation
                $result[$newKey] = method_exists($value, '__toString')
                    ? (string) $value
                    : get_class($value);
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }

    /**
     * Returns string of tags.
     * The string by default will send current environment.
     * To override this, you can use DATADOG_ENVIRONMENT
     * on you .env file.
     *
     * @return string
     */
    private function getTags(): string
    {
        $envString = env('DATADOG_ENVIRONMENT', app()->environment());

        return 'env:'.$envString;
    }

    /**
     * Translate Laravel error to DataDog error.
     *
     * @param  Level  $status
     * @return string
     */
    private function getLogStatus(Level $status): string
    {
        // convert to lowercase to prevent error
        $statusString = strtolower($status->getName());

        if (in_array($statusString, ['debug', 'info'])) {
            return 'info';
        }

        if (in_array($statusString, ['notice', 'warning'])) {
            return 'warn';
        }

        return 'error';
    }
}
