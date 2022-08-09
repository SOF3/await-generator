# Generators
A PHP function that contains a `yield` keyword is called a "generator function".

```php
function foo() {
	echo "hi!\n";
	yield;
	echo "working hard.\n";
	yield;
	echo "bye!\n";
}
```

When you call this function, it does not do anything
(it doesn't even echo "hi").
Instead, you get a [`Generator`](https://php.net/class.generator) object,
which lets you control the execution of the function.

Let's tell PHP to start running this function:

```php
$generator = foo();
echo "Let's start foo\n";
$generator->rewind();
echo "foo stopped\n";
```

You will get this output:

```
Let's start foo
hi!
foo stopped
```

The function stops when there is a `yield` statement.
We can tell the function to continue running using the `Generator` object:

```php
$generator->send(null);
```

And this additional output:
```
working hard.
```

Now it stops again at the next `yield`.

## Sending data into/out of the `Generator`
We can put a value behind the `yield` keyword to send data to the controller:

```php
function bar() {
	yield 1;
}
$generator = bar();
$generator->rewind();
var_dump($generator->current());
```

```
int(1)
```

Similarly, we can send data back to the function.
If you use `yield [value]` as an expression,
it is resolved into the value passed in `$generator->send()`.

```php
function bar() {
	$receive = yield;
	var_dump($receive);
}
$generator = bar();
$generator->rewind();
$generator->send(2);
```

```
int(2)
```

Furthermore, the function can eventually "return" a value.
This return value is not handled the same way as a `yield`;
it is obtained using `$generator->getReturn()`.
However, the return type hint must always be `Generator`
no matter what you return, or if you don't return:

```php
function qux(): Generator {
	yield 1;
	return 2;
}
```

## Calling another generator
You can call another generator in a generator,
which will pass through all the yielded values
and send back all the sent values
using the `yield from` syntax.
The `yield from` expression resolves to the return value of the generator.

```php
function test($value): Generator {
	$send = yield $value;
	return $send;
}

function main(): Generator {
	$a = yield from test(1);
	$b = yield from test(2);
	var_dump($a + $b);
}

$generator = main();
$generator->rewind();
var_dump($generator->current());
$generator->send(3);
var_dump($generator->current());
$generator->send(4);
```

```
int(1)
int(2)
int(7)
```

## Hacking generators
Sometimes we want to make a generator function that does not yield at all.
In that case, you can write `0 && yield;` at the start of the function;
this will make your function a generator function, but it will not yield anything.
As of PHP 7.4.0, `0 && yield;` is a no-op,
which means it will not affect your program performance
even if you run this line many times.

```php
function emptyGenerator(): Generator {
	0 && yield;
	return 1;
}

$generator = emptyGenerator();
var_dump($generator->next());
var_dump($generator->getReturn());
```

```
NULL
int(1)
```
