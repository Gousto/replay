<?php

namespace Gousto\Replay;

use Closure;
use Exception;

/**
 * Class Replay
 *
 * @package Gousto\Replay
 */
class Replay
{

    /**
     * @var int
     */
    private $counter = 1;

    /**
     * @var int
     */
    private $max_attempts_count = 1;

    /**
     * @var int
     */
    private $attempts_count = 0;

    /**
     * @var int
     */
    private $delay = 0;

    /**
     * @var bool
     */
    private $has_handler = true;

    /**
     * @var bool
     */
    private $errored = false;

    /**
     * @var Closure
     */
    protected $user_function;

    /**
     * @var Closure
     */
    protected $retry_handler;

    /**
     * @var array
     */
    protected $exception_targets = [Exception::class];

    /**
     * Retry constructor.
     *
     * @param int     $retries
     * @param Closure $closure
     * @param int     $delay
     * @param array   $exception_targets
     */
    public function __construct($retries = 1, Closure $closure, $delay = 0, $exception_targets = [])
    {
        $this->counter = $retries;
        $this->max_attempts_count = $retries;
        $this->user_function = $closure;
        $this->delay = $delay;
        $this->exception_targets = $exception_targets ?: $this->exception_targets;
    }

    /**
     * @param int     $attempts
     * @param Closure $closure
     * @param int     $delay
     * @param array   $exception_targets
     *
     * @return static
     */
    public static function times($attempts = 1, Closure $closure, $delay = 0, $exception_targets = [])
    {
        return new static($attempts, $closure, $delay, $exception_targets);
    }

    /**
     * @param int     $attempts
     * @param Closure $closure
     * @param int     $delay
     * @param array   $exception_targets
     *
     * @return mixed
     */
    public static function retry($attempts = 1, Closure $closure, $delay = 0, $exception_targets = [])
    {
        return static::times($attempts, $closure, $delay, $exception_targets)->play();
    }

    /**
     * @param bool $silent
     *
     * @return mixed|null
     * @throws RetryLogicException
     */
    public function play($silent = false)
    {
        try {
            do { $response = $this->next(); } while ($this->counter > 0);
            return $response;
        } catch(RetryLogicException $e) {
            if ($silent === true) {
                return $e;
            }
            throw $e;
        }
    }

    /**
     * @return mixed
     * @throws Exception|RetryLogicException
     */
    public function next()
    {
        try {
            $this->errored = false;
            return $this->invokeUserFunction();
        } catch ( Exception $e ) {

            $this->attempts_count++;
            $this->counter--;
            $this->errored = true;

            $this->handleError($e);

            // No handler specified, bubble up the exception
            if ( ! $this->has_handler) {
                throw $e;
            }

            // Counter is done
            if ($this->counter <= 0) {
                $this->counter = 0;
                $this->retryLogicException($e);
            }

            return $e;
        }
    }

    /**
     * @param Exception $e
     *
     * @throws Exception
     */
    protected function handleError(Exception $e)
    {
        $exception_targets = $this->exception_targets;

        // Loop through all the exception handlers
        // until we find a match
        foreach ($exception_targets as $error_handler) {
            if (is_a($e, $error_handler, true)) {
                $this->has_handler = true;

                // Any delay provided for every retry
                if ($this->delay > 0) {
                    usleep($this->delay * 1000);
                }

                // Any custom error handler specified
                if ($handler = $this->retry_handler) {
                    $handler($e, $this);
                }
                break;
            } else {
                $this->has_handler = false;
            }
        }
    }

    /**
     * Invoke user function
     *
     * @return mixed
     */
    private function invokeUserFunction()
    {
        $user_function = $this->user_function;
        $response      = $user_function();
        $this->counter = 0;
        $this->errored = false;

        return $response;
    }

    /**
     * @return static
     */
    public function newReplay()
    {
        return new static(
            $this->max_attempts_count,
            $this->user_function,
            $this->delay,
            $this->exception_targets
        );
    }

    /**
     * @param Closure $retry_handler
     *
     * @return $this
     */
    public function onRetry(Closure $retry_handler)
    {
        $this->retry_handler = $retry_handler;

        return $this;
    }

    /**
     * @return int
     */
    public function maxAttempts()
    {
        return $this->max_attempts_count;
    }

    /**
     * @return bool
     */
    public function isErrored()
    {
        return $this->errored;
    }

    /**
     * @return int
     */
    public function attempts()
    {
        return $this->attempts_count;
    }

    /**
     * @return int
     */
    public function getDelay()
    {
        return $this->delay;
    }

    /**
     * @param int $delay
     */
    public function setDelay($delay)
    {
        $this->delay = $delay;
    }

    /**
     * @param $e
     *
     * @throws RetryLogicException
     */
    protected function retryLogicException(Exception $e)
    {
        throw new RetryLogicException(
            $this,
            $e,
            "Retry logic failed after $this->max_attempts_count times"
        );
    }
}
