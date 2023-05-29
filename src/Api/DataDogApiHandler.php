<?php

namespace Shadowbane\DatadogLogger\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
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

    /** @var string */
    protected static string $ENDPOINT = 'https://http-intake.logs.datadoghq.com/api/v2/logs';

    /**
     * @param string $token API token supplied by DataDog
     * @param string|int $level The minimum logging level to trigger this handler
     * @param bool $bubble whether or not messages that are handled should bubble up the stack
     *
     * @throws MissingExtensionException If the curl extension is missing
     */
    public function __construct(string $token, $level = Logger::WARNING, bool $bubble = true)
    {
        if (!extension_loaded('curl')) {
            throw new MissingExtensionException('The curl extension is needed to use the DataDogApiHandler');
        }
        $this->token = $token;
        parent::__construct($level, $bubble);
    }

    /**
     * Write implementation of AbstractProcessingHandler.
     *
     * @param LogRecord $record
     *
     * @return void
     */
    protected function write(LogRecord $record): void
    {
        $this->send($record);
    }

    /**
     * Send the log.
     *
     * @param LogRecord $record
     *
     * @return void
     */
    protected function send(LogRecord $record): void
    {
        try {
            $client = new Client();
            $client->request(
                'POST',
                self::$ENDPOINT,
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'DD-API-KEY' => $this->token,
                    ],
                    'body' => json_encode($this->createBody($record)),
                ]
            );
        } catch (GuzzleException $e) {
            return;
        }
    }

    /**
     * Create the body of the log to send
     * to DataDog via the API.
     *
     * @param LogRecord $record
     *
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
        ];

        if (!blank($record->context) && $record->context['exception'] instanceof \Exception) {
            /** @var \Exception $exception */
            $exception = $record->context['exception'];
            $body['error.kind'] = $exception->getCode();
            $body['error.message'] = $exception->getMessage();
            $body['error.stack'] = $exception->getTraceAsString();

            // replace message with exception class
            $body['message'] = get_class($exception);
        }

        return $body;
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
     * @param Level $status
     *
     * @return string
     */
    private function getLogStatus(Level $status): string
    {
        // convert to lowercase to prevent error
        $status = strtolower($status->getName());

        if (in_array($status, ['debug', 'info'])) {
            return 'info';
        }

        if (in_array($status, ['notice', 'warning'])) {
            return 'warn';
        }

        return 'error';
    }
}
