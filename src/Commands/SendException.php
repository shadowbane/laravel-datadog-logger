<?php

namespace Shadowbane\DatadogLogger\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestException extends Exception
{
    /**
     * @param  string  $message
     * @param  int  $code
     * @param  Throwable|null  $previous
     */
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

class SendException extends Command
{
    protected $name = 'datadog:send-test-exception';
    protected $description = 'Test sending exception to datadog logger via API';

    /**
     * Execute the console command.
     *
     * @throws TestException
     */
    public function handle(): void
    {
        try {
            throw new TestException('This is test exception. Sent at: '.date('Y-m-d H:i:s'), 500);
        } catch (TestException $e) {
            // We catch the log, to prevent showing stacktrace on console, as this exception is expected.
            Log::channel('datadog-api')
                ->error($e->getMessage(), [
                    'exception' => $e,
                    'test' => 'data',
                ]);
            $this->error($e->getMessage());
        }
    }
}
