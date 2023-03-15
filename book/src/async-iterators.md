# Async iterators
In normal PHP functions, there is only a single return value.
If we want to return data progressively,
generators should have been used,
where the user can iterate on the returned generator.
However, if the user intends to perform async operations
in every step of progressive data fetching,
the `next()` method needs to be async too.
In other languages, this is called "async generator" or "async iterator".
However, since await-generator has hijacked the generator syntax,
it is not possible to create such structures directly.

Instead, await-generator exposes the `Traverser` class,
which is an extension to the normal await-generator syntax,
providing an additional yield mode `Traverser::VALUE`,
which allows an async function to yield async iteration values.
A key (the current traversed value) is passed with `Traverser::VALUE`.
The resultant generator is wrapped with the `Traverser` class,
which provides an asynchronous `next()` method that
executes the generator asynchronously and returns the next traversed value,

## Example
In normal PHP, we may have an line iterator on a file stream like this:

```php
function lines(string $file) : Generator {
	$fh = fopen($file, "rt");
	try {
		while(($line = fgets($fh)) !== false) {
			yield $line;
		}
	} finally {
		fclose($fh);
	}
}

function count_empty_lines(string $file) {
	$count = 0;
	foreach(lines($file) as $line) {
		if(trim($line) === "") $count++;
	}
	return $count;
}
```

What if we have async versions of `fopen`, `fgets` and `fclose`
and want to reimplement this `lines` function as async?

We would use the `Traverser` class instead:

```php
function async_lines(string $file) : Generator {
	$fh = yield from async_fopen($file, "rt");
	try {
		while(true) {
			$line = yield from async_fgets($fh);
			if($line === false) {
				return;
			}
			yield $line => Traverser::VALUE;
		}
	} finally {
		yield from async_fclose($fh);
	}
}

function async_count_empty_lines(string $file) : Generator {
	$count = 0;

	$traverser = new Traverser(async_lines($file));
	while(yield from $traverser->next($line)) {
		if(trim($line) === "") $count++;
	}

	return $count;
}
```

## Interrupting a generator
Yielding inside `finally` may cause a crash
if the generator is not yielded fully.
If you perform async operations in the `finally` block,
you **must** drain the traverser fully.
If you don't want the iterator to continue executing,
you may use the `yield $traverser->interrupt()` method,
which keeps throwing the first parameter
(`SOFe\AwaitGenerator\InterruptException` by default)
into the async iterator until it stops executing.
Beware that `interrupt` may throw an `AwaitException`
if the underlying generator catches exceptions during `yield Traverser::VALUE`s
(hence consuming the interrupts).

It is not necessary to interrupt the traverser
if there are no `finally` blocks containing `yield` statements.
