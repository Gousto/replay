# Replay

[![CircleCI](https://img.shields.io/circleci/project/github/Gousto/replay.svg)](https://circleci.com/gh/Gousto/replay)
[![GitHub tag](https://img.shields.io/github/tag/Gousto/replay.svg)]()
[![Scrutinizer](https://img.shields.io/scrutinizer/g/Gousto/replay.svg)](https://scrutinizer-ci.com/g/Gousto/replay)

Re-play functions that error out! 

Some times things not always works at the first attempt. 
**Replay** comes in allowing you to plan **re-tries** and **fallbacks** strategies for your
functions.

## Install

```bash
composer require gousto/replay
```

## Features

- Re-try functions within exception scope
- Increase delay for every attempt
- Create advanced re-try fallback strategies

## Usage

If you want to replay a function when it throws an **Exception** here is an example:
```php
<?php

use Gousto\Replay\Replay;

try {

    $result = Replay::retry(3, function() {
       return Http::get('example.com'); 
    }, 50);
    
    Log::info("All good");
} catch(\Gousto\Replay\RetryLogicException $e) {
    \Log::error("After 3 times the function still error out!");
}

```
This code will return you right away the result of `Http::get()`, if no **Exception** is occured.

In case of **Exception** it will retry again up to **3** times with a `50ms` interval.

### Increase delay at every attempt:
Let's say we want to have a re-try strategy that want to increase the interval at every attempt
`i.e`: 1st attempt `10ms`, 2nd attempts `20ms` 3rd attempt `40ms`

```php
<?php

use Gousto\Replay\Replay;

$replay = Replay::times(3, function() {

    throw new LogicException();
  
}, 10);

$replay->onRetry(function(Exception $e, Replay $replay) {
   $replay->setDelay($replay->getDelay() * 2); 
});

// play the strategy!
$replay->play();
```

### Custom Exceptions
Replay allow you to trigger the retry logic only for particular **Exceptions**,
pass an array of Exceptions class names as 4th paramter:

```php
<?php

use Gousto\Replay\Replay;

// Will throw LogicException and not retry
Replay::retry(3, function() {

    throw new LogicException();
  
}, 0, [RuntimeException::class]);
```
This will throw a `LogicException` when the function is invoked. 
We are going to retry only when the `RuntimeException` is thrown.

## API

### - retry($attempts = 1, Closure $user_function, $delay = 0, $exception_targets = [Exception::class])
Return the result of the Closure if no exception is occured. Alternatevely it retries invoking the
function until the number of attempts specified. Throw `Gousto\Replay\RetryLogicException::class` if the number
of attempts remained is 0.

- `$attempts`: int, number of attempts the function might be replayed
- `$user_function:` Closure, function which will be replayed if errored
- `$delay:` int, delay is measured in milliseconds
- `$exception_targets:` array, list of exceptions which will be considered as errors

```php
use Gousto\Replay\Replay;

Replay::retry(1, function() {

    throw new Exception();
  
}, 0, [Exception::class]);
```
### - times($attempts = 1, Closure $user_function, $delay = 0, $exception_targets = [])
Helper function for instantiate **Replay**, facilitate chaining

- `$attempts`: int, number of attempts the function might be replayed
- `$user_function:` Closure, function which will be replayed if errored
- `$delay:` int, delay is measured in milliseconds
- `$exception_targets:` array, list of exceptions which will be considered as errors

```php
use Gousto\Replay\Replay;

$replay = Replay::times(1, function() {

    throw new Exception();
  
}, 0, [Exception::class]);

$replay->play();
```

### play($silent = false):
Tell replay to invoke the function and use the retry logic if needed.

- `$silent:` bool, if true will not throw `RetryLogicException` but will return it.

```php
use Gousto\Replay\Replay;

$replay = Replay::times(2, function() {

    throw new Exception();
  
}, 0, [Exception::class]);

$result = $replay->play(true); // $result instanceof RetryLogicException

$replay->play(); // throw RetryLogicException
```

### onRetry(Closure $handler):
Hook a function that will be trigger for every attempt, this is useful for logging or
any other action.

```php
use Gousto\Replay\Replay;

$counter = 0;
$replay = Replay::times(3, function() use (&$counter) {
    $counter++;
    if ($counter === 3) {
        return $counter;
    }
    throw new Exception("Error"); 
});

// WILL LOG:
// Attempt 1: Failed
// Attempt 2: Failed
$replay->onRetry(function(Exception $exception, Replay $replay) {
   Log::error("Attempt {$replay->attempts()}: Failed"); 
});

try {
    $replay->play();
} catch(\Gousto\Replay\RetryLogicException $e) {
    
}
```

### next():
The next function allow you to manually control the retry cycle

```php
use Gousto\Replay\Replay;

$replay = Replay::times(3, function() {

    throw new Exception();
  
}, 0, [Exception::class]);

$replay->next(); // 1 attempt
$replay->next(); // 2 attempts
$replay->next(); // Throw RetryLogicException
```

### attempts():
Return the number of retry attempts
```php
use Gousto\Replay\Replay;

$replay = Replay::times(3, function() {

    throw new Exception();
  
}, 0, [Exception::class]);

$replay->next();
$replay->attempts(); // 1

$replay->next();
$replay->attempts(); // 2
```


### isErrored():
Return the state of the function invokation

```php
$replay = Replay::times(4, function() {

    return 10;
  
}, 0, [Exception::class]);

$result = $replay->play(); // 10

$replay->isErrored(); // False
```

### Credits

www.gousto.co.uk
