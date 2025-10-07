<?php

namespace Shadowbane\DatadogLogger\Api;

use Monolog\Formatter\LineFormatter;

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
        foreach ($logger->getHandlers() as $handler) {
            $handler->setFormatter(new LineFormatter(
                '%channel%.%level_name%: %message%\ncontext: %context%\nextra: %extra%'
            ));
        }
    }
}
