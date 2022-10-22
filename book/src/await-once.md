# Using callback-style from generators
Although it is easier to work with generator functions,
ultimately, you will need to work with functions that do not use await-generator.
In that case, callbacks are easier to use.
A callback `$resolve` can be acquired using `Await::promise`.

```php
function a(Closure $callback): void {
	// The other function that uses callbacks.
	// Let's assume this function will call $callback("foo") some time later.
}

function main(): Generator {
	return yield from Await::promise(fn($resolve) => a($resolve));
}
```

Some callback-style async functions may accept another callback for exception handling. This callback can be acquired by taking a second parameter `$reject`.

```php
function a(Closure $callback, Closure $onError): void {
	// The other function that uses callbacks.
	// Let's assume this function will call $callback("foo") some time later.
}

function main(): Generator {
	return yield from Await::promise(fn($resolve, $reject) => a($resolve, $reject));
}
```

## Example
Let's say we want to make a function that sleeps for 20 server ticks,
or throws an exception if the task is cancelled:

```php
use pocketmine\scheduler\Task;

public function sleep(): Generator {
	yield from Await::promise(function($resolve, $reject) {
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
	});
}
```

This is a bit complex indeed, but it gets handy once we have this function defined!
Let's see what we can do with a countdown:

```php
function countdown($player) {
	for($i = 10; $i > 0; $i--) {
		$player->sendMessage("$i seconds left");
		yield from $this->sleep();
	}

	$player->sendMessage("Time's up!");
}
```

This is much simpler than using `ClosureTask` in a loop!
