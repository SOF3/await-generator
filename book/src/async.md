# Asynchronous programming
Traditionally, when you call a function,
it performs the required actions and returns after they're done.
In asynchronous programming,
the program logic may be executed *after* a function returns.

This leads to two problems.
First, the function can't return you with any useful results,
because the results are only available after the logic completes.
Second, you may do something else assuming the logic is completed,
which leads to a bug.
For example:

```php
private $data;

function loadData($player) {
	// we will set $this->data[$player] some time later.
}

function main() {
	$this->loadData("SOFe");
	echo $this->data["SOFe"]; // Undefined offset "SOFe"
}
```

Here, `loadData` is the function that loads data asynchronously.
`main` is implemented incorrectly, assuming that `loadData` is synchronous,
i.e. it assumes that `$this->data["SOFe"]` is initialized.

## Using callbacks
One of the simplest ways to solve this problem is to use callbacks.
The caller can pass a closure to the async function,
then the async function will run this closure when it has finished.
An example function signature would be like this:

```php
function loadData($player, Closure $callback) {
	// $callback will be called when player data have been loaded.
}

function main() {
	$this->loadData("SOFe", function() {
		echo $this->data["SOFe"]; // this is guaranteed to work now
	});
}
```

The `$callback` will be called when some other logic happens.
This depends on the implementation of the `loadData` logic.
This may be when a player sends a certain packet,
or when a scheduled task gets run,
or other scenarios.

### More complex callbacks
(This section is deliberately complicated and hard to understand,
because the purpose is to tell you that using callbacks is bad.)

What if we want to call multiple async functions one by one?
In synchronous code, it would be simple:
```php
$a = a();
$b = b($a);
$c = c($b);
$d = d($c);
var_dump($d);
```

In async code, we might need to do this (let's say `a`, `b`, `c`, `d` are async):

```php
a(function($a) {
	b($a, function($b) {
		c($b, function($c) {
			d($c, function($d) {
				var_dump($d);
			});
		});
	});
});
```

Looks ugly, but readable enough.
It might look more confusing if we need to pass `$a` to `$d` though.

But what if we want to do if/else?
In synchronous code, it looks like this:
```php
$a = a();
if($a !== null) {
	$output = b($a);
} else {
	$output = c() + 1;
}

$d = very_complex_code($output);
$e = that_deals_with($output);
var_dump($d + $e + $a);
```

In async code, it is much more confusing:
```php
a(function($a) {
	if($a !== null) {
		b($a, function($output) use($a) {
				$d = very_complex_code($output);
				$e = that_deals_with($output);
				var_dump($d + $e + $a);
		});
	} else {
		c(function($output) use($a) {
				$output = $output + 1;
				$d = very_complex_code($output);
				$e = that_deals_with($output);
				var_dump($d + $e + $a);
		});
	}
});
```

But we don't want to copy-paste the three lines of duplicated code.
Maybe we can assign the whole closure to a variable:

```php
a(function($a) {
	$closure = function($output) use($a) {
		$d = very_complex_code($output);
		$e = that_deals_with($output);
		var_dump($d + $e + $a);
	};

	if($a !== null) {
		b($a, $closure);
	} else {
		c(function($output) use($closure) {
			$closure($output + 1);
		});
	}
});
```

Oh no, this is getting out of control.
Think about how complicated this would become when
we want to use asynchronous functions in loops!

The await-generator library allows users to write async code in synchronous style.
As you might have guessed, the `yield` keyword is a replacement for callbacks.
