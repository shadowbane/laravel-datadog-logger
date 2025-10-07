<?php

namespace Shadowbane\DatadogLogger\Api;

use Monolog\Handler\MissingExtensionException;
use Monolog\Logger;
use Shadowbane\DatadogLogger\ApiKeyNotFoundException;

/**
 * Class DataDogApiHandler.
 */
class CreateDataDogApiLogger
{
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
        $dataDogHandler = new DataDogApiHandler(
            $config['apiKey'],
            $config['level'] ?? Logger::WARNING,
            $config['bubble'] ?? true
        );

        return (new Logger('datadog-api'))
            ->pushHandler($dataDogHandler);
    }
}
