<?php

namespace Shadowbane\DatadogLogger\Providers;

use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\ServiceProvider;
use Shadowbane\DatadogLogger\Api\CreateDataDogApiLogger;

/**
 * Class DataDogServiceProvider.
 */
class DataDogServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->commands([
            \Shadowbane\DatadogLogger\Commands\SendException::class,
        ]);

        // Buffered logs are flushed at PHP shutdown, which a queue worker
        // reaches only when it exits. Flush after every job instead, and on
        // terminate for HTTP and console runs.
        $this->app['events']->listen(
            [JobProcessed::class, JobExceptionOccurred::class, JobFailed::class],
            fn () => CreateDataDogApiLogger::flushBuffers()
        );
        $this->app->terminating(fn () => CreateDataDogApiLogger::flushBuffers());
    }

    /**
     * Register laravel-datadog-logger,
     * merge configuration into the logging.channels array.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/logging.php',
            'logging.channels'
        );
    }
}
