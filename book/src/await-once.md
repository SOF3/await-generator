# Using callback-style from generators
Although it is easier to work with generator functions,
ultimately, you will need to work with functions that do not use await-generator.
In that case, callbacks are easier to use.

Using callback-style functions involves two steps.
First, you create a callback passed to the function.
Second, you tell await-generator to pause until the callback is called.

The first step can be done simply by writing `yield`.
Upon yielding no values (or `null`),
await-generator will immediately resume your function
and send you a callback that will notify await-generator about function completion.

To achieve the second step, you have to yield a constant `Await::ONCE`.
This tells await-generator to resume your function
when the previous callback from `yield` is called.

```php
function a(Closure $callback): void {
	// The other function that uses callbacks.
	// Let's assume this function will call $callback("foo") some time later.
}

function main(): Generator {
	$callback = yield;
	yield Await::ONCE;
}
```

Some callback-style async functions may also accept an `$onError` callback parameter.
This callback can be created by calling `Await::REJECT`.
Then `Await::ONCE` will call your function 

```
function a(Closure $callback, Closure $onError): void {
	// The other function that uses callbacks.
	// Let's assume this function will call $callback("foo") some time later.
}

function main(): Generator {
	$callback = yield;
	yield Await::ONCE;
}
```

## Example
Let's say we want to make a function that sleeps for 20 server ticks,
or throws an exception if the task is cancelled:

```php
use pocketmine\scheduler\Task;

public function sleep(): Generator {
	$resolve = yield;
	$reject = yield Await::REJECT;
	$task = new class($resolve, $reject) extends Task {
		private $resolve;
		private $reject;
		public function __construct($resolve, $reject) {
			$this->resolve = $resolve;
			$this->reject = $reject;
		}
		public function onRun(int $tick) {
			($this->resolve)();
		}
		public function onCancel() {
			($this->reject)(new \Exception("Task cancelled"));
		}
	};
	$this->getServer()->getScheduler()->scheduleDelayedTask($task, 20);

	yield Await::ONCE;
}
```

This is a bit complex indeed, but it gets handy once we have this function defined!
Let's see what we can do with a countdown:

```php
function countdown($player) {
	for($i = 10; $i > 0; $i--) {
		$player->sendMessage("$i seconds left");
		yield $this->sleep();
	}

	$player->sendMessage("Time's up!");
}
```

This is much simpler than using `ClosureTask` in a loop!
