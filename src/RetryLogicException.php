<?php

namespace Gousto\Replay;

use Exception;

/**
 * Class RetryLogicException
 *
 * @package Gousto\Replay
 */
class RetryLogicException extends Exception
{
    /**
     * @var Exception
     */
    protected $original_error;

    /**
     * @var Replay
     */
    protected $replay;

    /**
     * RetryLogicException constructor.
     *
     * @param Replay    $replay
     * @param Exception $original_error
     * @param int       $message
     */
    public function __construct(Replay $replay, Exception $original_error, $message)
    {
        parent::__construct($message);
        $this->original_error = $original_error;
        $this->replay = $replay;
        $this->message = $message;
    }

    /**
     * @return Exception
     */
    public function getOriginalError()
    {
        return $this->original_error;
    }

    /**
     * @return Replay
     */
    public function getReplay()
    {
        return $this->replay;
    }
}
