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
Await::f2c(function(): Generator {
	// some async logic
});
```

You can also use `Await::g2c`/`Await::f2c`
to schedule a separate async function in the background.
