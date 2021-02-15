# Exposing a generator to normal API
Recall that generator functions do not do anything when they get called.
Eventually, we have to call the generator function from a non-await-generator context.
We can use the `Await::g2c` function for this:

```php
private function generateFunction(): Generator {
	// some async logic
}

Await::g2c($this->generatorFunction());
```

Sometimes we want to write the generator function as a closure
and pass it directly:

```php
Await::f2c(function(): \Generator {
	// some async logic
});
```

The return/throw value of the generator can be handled in callback style too:

```php
Await::f2c(function(): \Generator {
	0 && yield;
	if(rand() % 2 == 1) {
		return 1;
	} else {
		throw new \Exception("random");
	}
}, function($value) {
	var_dump($value); // int(1)
}, function($ex) {
	var_dump($ex->getMessage()); // string(6) "random"
});
```
