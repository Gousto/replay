<?php

use Gousto\Replay\Replay;
use Gousto\Replay\RetryLogicException;

/**
 * Class ReplayCest
 */
class ReplayCest
{

    public function willReturnTheContentOfTheUserFunctionAtTheFirstInvokation(UnitTester $I)
    {
        $replay = new Replay(3, function() {
            return 10;
        });

        $result = $replay->play(true);

        $I->assertEquals(0, $replay->attempts());
        $I->assertEquals(3, $replay->maxAttempts());
        $I->assertEquals(10, $result);
        $I->assertFalse($replay->isErrored());

        $new_replay = $replay->newReplay();
        $new_result = $new_replay->play();

        $I->assertEquals(0, $new_replay->attempts());
        $I->assertEquals(3, $new_replay->maxAttempts());
        $I->assertEquals(10, $new_result);
        $I->assertFalse($new_replay->isErrored());
    }

    public function willSilentlyFailReturningTheException(UnitTester $I)
    {
        $replay = new Replay(3, function() {
            throw new LogicException();
        });

        $result = $replay->play(true);

        $I->assertEquals(3, $replay->attempts());
        $I->assertEquals(3, $replay->maxAttempts());
        $I->assertTrue($replay->isErrored());

        $I->assertInstanceOf(RetryLogicException::class, $result);
    }

    public function WillThrowWhenReplayLogicIsExceeded(UnitTester $I)
    {
        $I->expectException(RetryLogicException::class, function() {
            Replay::retry(3, function() {
                throw new Exception("Error");
            });
        });

        $I->expectException(RetryLogicException::class, function() {
            $replay = new Replay(3, function() {
                throw new Exception("Error");
            });

            $replay->play();
        });

        try {
            $replay = new Replay(3, function() {
                throw new LogicException("Error");
            });

            $replay->play();
        } catch(RetryLogicException $e) {
            $I->assertInstanceOf(Replay::class, $e->getReplay());
            $I->assertInstanceOf(LogicException::class, $e->getOriginalError());
        }
    }

    public function WillInvokeUserFunctionTwoTimesThenReturnTheResult(UnitTester $I)
    {
        $value = 0;
        $replay = new Replay(4, function() use (&$value) {
            if ($value < 2) {
                $value++;
                throw new Exception("Error");
            }
            return $value;
        });

        $result = $replay->play(false);

        $I->assertEquals(2, $result);
        $I->assertEquals(2, $replay->attempts());
        $I->assertEquals(4, $replay->maxAttempts());
        $I->assertFalse($replay->isErrored());
    }

    public function willRetryOnlyForSpecificExceptions(UnitTester $I)
    {
        $I->expectException(RetryLogicException::class, function() {
            Replay::retry(3,function() {
                throw new RuntimeException();
            }, 0, [RuntimeException::class]);
        });

        $I->expectException(RetryLogicException::class, function() {
            $replay = new Replay(3, function() use (&$times) {
                throw new RuntimeException("Error");
            }, 0, [RuntimeException::class]);

            $replay->play();
        });
    }

    public function willRetryOnlyForSpecificExceptionsThrowingOriginal(UnitTester $I)
    {
        $I->expectException(new LogicException("Error"), function() {
            Replay::retry(3,function() {
                throw new LogicException("Error");
            }, 0, [RuntimeException::class]);
        });

        $I->expectException(new LogicException("Error"), function() {
            $replay = new Replay(3, function() {
                throw new LogicException("Error");
            }, 0, [RuntimeException::class]);

            $replay->play();
        });
    }

    public function willCallARetryFunctionOnEveryRetry(UnitTester $I)
    {
        $replay = new Replay(3, function() {
            throw new RuntimeException("Error");
        });

        $called = 0;
        $replay->onRetry(function(Exception $e, Replay $replay) use ($I, &$called) {
            $I->assertInstanceOf(RuntimeException::class, $e);
            $I->assertInstanceOf(Replay::class, $replay);
            $called++;
        });

        $replay->play(true);

        $I->assertEquals(3, $called);
    }

    public function willDelayExecutionBetweenRetries(UnitTester $I)
    {
        $replay = Replay::times(3, function() {
            throw new RuntimeException();
        }, 50); // 50ms

        $start_time = $this->microtime_float();

        $replay->play(true);

        $end_time = $this->microtime_float();
        $end = $end_time - $start_time;

        $I->assertTrue($end > 0.150);
    }

    public function canControlRetryAttempts(UnitTester $I)
    {
        $replay = Replay::times(4, function() {
            throw new RuntimeException("Error");
        });

        $result = $replay->next();
        $I->assertInstanceOf(RuntimeException::class, $result);
        $I->assertEquals(1, $replay->attempts());

        $result = $replay->next();
        $I->assertInstanceOf(RuntimeException::class, $result);
        $I->assertEquals(2, $replay->attempts());

        $result = $replay->next();
        $I->assertInstanceOf(RuntimeException::class, $result);
        $I->assertEquals(3, $replay->attempts());

        $I->expectException(RetryLogicException::class, function() use ($replay){
            $replay->next();
        });

        $I->expectException(RetryLogicException::class, function() use ($replay) {
            $replay->play();
        });

        try {
            $replay->next();
        } catch(RetryLogicException $e) {
            $I->assertEquals(6, $replay->attempts());
        }
    }

    /**
     * @return float
     */
    protected function microtime_float()
    {
        list($usec, $sec) = explode(" ", microtime());
        $start = ((float) $usec + (float) $sec);

        return $start;
    }
}
