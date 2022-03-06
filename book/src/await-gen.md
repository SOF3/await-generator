# Awaiting generators
Since every async function is implemented as a generator function,
simply calling it will not have any effects.
Instead, you have to `yield from` the generator.

```php
function a(): Generator {
	// some other async logic here
	return 1;
}

function main(): Generator {
	$a = yield from $this->a();
	var_dump($a);
}
```

It is easy to forget to `yield from` the generator.
<!-- TODO provide suggestions -->
<!-- TODO does phpstan detect this? -->

## Handling errors
`yield from` will throw an exception
if the generator function you called threw an exception.

```php
function err(): Generator {
	// some other async logic here
	throw new Exception("Test");
}

function main(): Generator {
	try {
		yield from err();
	} catch(Exception $e) {
		var_dump($e->getMessage()); // string(4) "Test"
	}
}
```
