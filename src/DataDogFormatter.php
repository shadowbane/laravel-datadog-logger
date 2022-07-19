<?php

namespace Shadowbane\DatadogLogger;

use DateTime;
use Monolog\Formatter\JsonFormatter;

/**
 * Class DataDogFormatter
 *
 * @extends JsonFormatter
 */
class DataDogFormatter extends JsonFormatter
{

    const LARAVEL_LOG_DATETIME_KEY = 'datetime';

    /**
     * Appends every variable needed by DataDog
     *
     * @TODO Handle trace_id for projects linked with DataDog APM
     *
     * @param array $record
     *
     * @return string
     * @see  \Monolog\Formatter\JsonFormatter::format()
     * @see  https://docs.datadoghq.com/logs/processing/#reserved-attributes
     */
    public function format(array $record)
    {
        if (isset($record[self::LARAVEL_LOG_DATETIME_KEY]) &&
            ($record[self::LARAVEL_LOG_DATETIME_KEY] instanceof DateTime)) {
            /**
             * @var DateTime $dateTimeObj
             */
            $dateTimeObj              = $record[self::LARAVEL_LOG_DATETIME_KEY];
            $record['published_date'] = $dateTimeObj->format(DateTime::ISO8601);
        }

        if (isset($record['level_name'])) {
            $record['status'] = $record['level_name'];
        }
        $record['ddsource'] = 'php-' . php_sapi_name();
        $record['source']   = 'php-' . php_sapi_name();
        $record['service']  = config('app.name');
        $record['hostname'] = gethostname();

        return parent::format($record);
    }
}
