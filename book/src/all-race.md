# Running generators concurrently
In addition to calling multiple generators sequentially,
you can also use `Await::all()` or `Await::race()` to run multiple generators.

If you have a JavaScript background, you can think of `Generator` objects as promises
and `Await::all()` and `Await::race()` are just `Promise.all()` and `Promise.race()`.

## `Await::all()`
`Await::all()` allows you to run an array of generators at the same time.
If you yield `Await::all($array)`, your function resumes when
all generators in `$array` have finished executing.

```php
function loadData(string $name): Generator {
	// some async logic
	return strlen($name);
}

$array = [
	"SOFe" => $this->loadData("SOFe"), // don't yield it yet!
	"PEMapModder" => $this->loadData("PEMapModder"),
];
$results = yield from Await::all($array);
var_dump($result);
```

Output:
```
array(2) {
  ["SOFe"]=>
  int(4)
  ["PEMapModder"]=>
  int(11)
}
```

Yielding `Await::all()` will throw an exception
as long as *any* of the generators throw.
The error condition will not wait until all generators return.

## `Await::race()`
`Await::race()` is like `Await::all()`,
but it resumes as long as *any* of the generators return or throw.
The returned value of `yield from` is a 2-element array containing the key and the value.

```php
function sleep(int $time): Generator {
	// Let's say this is an await version of `scheduleDelayedTask`
	return $time;
}

function main(): Generator {
	[$k, $v] = yield from Await::race([
		"two" => $this->sleep(2),
		"one" => $this->sleep(1),
	]);
	var_dump($k); // string(3) "one"
	var_dump($v); // int(1)
}
```
