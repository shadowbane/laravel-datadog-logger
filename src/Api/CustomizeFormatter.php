<?php

namespace Shadowbane\DatadogLogger\Api;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\FormattableHandlerInterface;
use Monolog\Logger;

class CustomizeFormatter
{
    /**
     * Customize the given logger instance.
     *
     * @param  \Illuminate\Log\Logger  $logger
     * @return void
     */
    public function __invoke($logger)
    {
        $monolog = $logger->getLogger();
        if (! $monolog instanceof Logger) {
            return;
        }

        foreach ($monolog->getHandlers() as $handler) {
            if (! $handler instanceof FormattableHandlerInterface) {
                continue;
            }

            $handler->setFormatter(new LineFormatter(
                '%channel%.%level_name%: %message%\ncontext: %context%\nextra: %extra%'
            ));
        }
    }
}
