<?php

namespace Shadowbane\DatadogLogger\Api;

use Monolog\Handler\BufferHandler;
use Monolog\Handler\MissingExtensionException;
use Monolog\Logger;
use WeakMap;
use Shadowbane\DatadogLogger\ApiKeyNotFoundException;

/**
 * Class DataDogApiHandler.
 */
class CreateDataDogApiLogger
{
    /**
     * Buffers created by this factory, so long-running processes can flush
     * them between units of work. Weakly held: a forgotten channel is freed.
     *
     * @var WeakMap<BufferHandler, true>|null
     */
    private static ?WeakMap $buffers = null;

    /**
     * Create the DataDog Api Logger.
     *
     * @param  array  $config
     *
     * @throws ApiKeyNotFoundException
     * @throws MissingExtensionException
     *
     * @return Logger
     */
    public function __invoke(array $config): Logger
    {
        if (empty($config['apiKey'])) {
            throw new ApiKeyNotFoundException;
        }
        $level = $config['level'] ?? Logger::WARNING;
        $bubble = filter_var($config['bubble'] ?? true, FILTER_VALIDATE_BOOL);

        $dataDogHandler = new DataDogApiHandler($config['apiKey'], $level, $bubble);

        if (! filter_var($config['buffer'] ?? true, FILTER_VALIDATE_BOOL)) {
            return (new Logger('datadog-api'))->pushHandler($dataDogHandler);
        }

        // Hold records in memory and send them in one batch when the buffer is
        // flushed: at PHP shutdown (after the response on php-fpm), after each
        // queued job, or early if a single request logs more than 1000 records.
        $buffer = new BufferHandler($dataDogHandler, 1000, $level, $bubble, true);

        self::$buffers ??= new WeakMap;
        self::$buffers[$buffer] = true;

        return (new Logger('datadog-api'))->pushHandler($buffer);
    }

    /**
     * Send everything currently buffered.
     *
     * @return void
     */
    public static function flushBuffers(): void
    {
        foreach (self::$buffers ?? [] as $buffer => $_) {
            $buffer->flush();
        }
    }
}
