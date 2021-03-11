# Awaiting generators
Since every async function is implemented as a generator function,
simply calling it will not have any effects.
Instead, you have to `yield` the generator.
await-generator will still resume your function
when the yielded generator finished running,
and send you the value returned by the yielded generator.

```php
function a(): Generator {
	// some other async logic here
	return 1;
}

function main(): Generator {
	$a = yield $this->a();
	var_dump($a);
}
```

It is easy to forget to `yield` the generator.
<!-- TODO provide suggestions -->
<!-- TODO does phpstan detect this? -->

## Handling errors
await-generator will make your `yield` throw an exception
if the generator function you called threw an exception.
You can use try-catch to handle these exceptions.

```php
function err(): Generator {
	// some other async logic here
	throw new Exception("Test");
}

function main(): Generator {
	try {
		yield err();
	} catch(Exception $e) {
		var_dump($e->getMessage()); // string(4) "Test"
	}
}
```
