# await-generator [![Build Status](https://travis-ci.org/SOF3/await-generator.svg?branch=master)](https://travis-ci.org/SOF3/await-generator) [![Codecov](https://img.shields.io/codecov/c/github/codecov/example-python.svg)](https://codecov.io/gh/SOF3/await-generator)

A library/framework to use async/await in PHP using generators.

## Tutorial
This section is a step-by-step tutorial for newcomers with only basic PHP knowledge.
If you already understand the concept of await-generator
and just want to find reference for some precise behaviour,
head to [SPEC.md](./SPEC.md).

### What is await-generator?
await-generator plays tricks on a PHP feature called "generators"
to make it easier to write code in a style called "async".
First, let's look at what are generators and async.

#### Generators
A PHP function which contains a `yield` keyword is called a "generator function".
When a generator function is called, the function code is not executed immediately.
Instead, a `Generator` object is returned,
which can be used to control when the function code starts/resumes running.

A `yield;` statement is similar to `return`,
but instead of stopping the function,
it just pauses ("suspends") the function.
The code holding the `Generator` object can *resume* the function
as if it never stopped.

A `yield` statment can be followed by a value,
i.e. have the syntax `yield $value;`.
This will send `$value` to the `Generator` holder.

`yield` can also be used as an expression.
If you write `$result = yield $value;`,
when the `Generator` holder resumes the function,
it can send back a value, which you will store in `$result`.

Analogously, `yield` is like asking a question,
and the function pauses until the question gets answered.
The `Generator` holder can answer the questions.

In this library, the user writes a generator function and gives the `Generator` to await-generator.
await-generator will use the yielded values to decide when the function pauses and resumes.

> To learn more about generators, see [the PHP documentation on generators](https://php.net/generators).

#### What is async?
Async (opposite of "sync") means that a function returns before getting the value.
For example, `file_get_contents` is a sync function,
because it always blocks until the file contents are available,
and returns with the file contents.
The hard drive might take a long time to read all the file contents,
so `file_get_contents` might take a long time and "block the main thread".
On the other hand, the async version of `file_get_contents` would return immediately.

So how does the user get the file contents?
Traditionally, the async function would take a `callable`/`Closure` as a parameter
(usually with the name `$onSuccess`, `$onComplete`, etc.),
and call this callable with the file contents when they are ready.
This allows the program to do other things without "blocking" on the file contents.
Furthermore, high-quality code would also handle errors properly,
so they might take another parameter (usually with the name `$onError`)
that gets called when an error occurs during the operation.

For example, an async function for reading file contents might look like this:
```php
function async_file_get_contents(string $file, callable $onSuccess, callable $onError) {
	// ...
}
```

Then you can call the function like this:
```php
async_file_get_contents("a.txt", function($data) {
	// use the data here
}, function($exception) {
	// handle exception here
});
```

(How `async_file_get_contents` is implemented internally is not the focus here;
a correct implementation might require threading or other complicated mechanisms,
but we just assume this function already exists for now)

Let's look at an example.
We have a list of file names in a file called names.txt,
and we want to know the total number of words in the listed files.
The original sync code would be written like this:

```php
$names = file_get_contents("names.txt");
$totalLength = 0;
foreach(explode(",", $names) as $name) {
	$data = file_get_contents($name);
	$totalLength += count(explode("\n", $data));
}
echo "There are $totalLength lines.";
```

The async code would look a bit little more complex
(the variable names should mean the same as above):

```php
async_file_get_contents("names.txt", function($names) {
	$totalLength = 0;
	foreach(explode(",", $names) as $name) {
		async_file_get_contents($name, function($data) use(&$totalLength) {
			// &$totalLength means that changing $totalLength inside this function also changes the one outside.
			$totalLength += count(explode("\n", $data));
		})
	}
	echo "There are $totalLength lines.";
});
```

(There should be `$onError` parameters too, but I omitted them because I am lazy :stuck_out_tongue:)

Doesn't look too bad. But it could sometimes get bad.
There is a situation called "callback hell".
Let's see what happens if every function call depends on the previous one:

```php
$a = file_get_contents("names.txt");
$b = file_get_contents($a);
$c = file_get_contents($b);
$d = file_get_contents($c);
echo $d;
```

The async code would become:

```php
async_file_get_contents("names.txt", function($a) {
	async_file_get_contents($a, function($b) {
		async_file_get_contents($b, function($c) {
			async_file_get_contents($c, function($d) {
				echo $d;
			});
		});
	});
});
```

This looks ugly. But this is not the worst.
If you think writing code in this async style is easy,
try translating the following sync codes to async
(i.e. if `a`, `b` and `c` become async functions):

```php
// Challenge 1: If/Else
$a = a();
if($a === null) {
	$a = b();
} else {
	$a = c($a);
}
echo $a;

// Challenge 2: Nested if
$a = a();
if($a === null) {
	$a = b();
	if($a === null) {
		$a = c();
	}
}
echo $a;

// Challenge 3: While loop
$a = a();
while($a === null) {
	$a = b($a);
}

// Challenge 4: For loop
$array = a();
foreach($array as $a) {
	echo b($a);
}
```

Challenge 4 is different from the total-length example above,
because it requires you to run `b` one by one
instead of running everything together.

Challenge 5: Similar to challenge 4, but what if I only want to output the first one?

I am not writing down the answers,
because the point of await-generator is to prevent the need to answer these questions :upside_down_face:

### The await-generator style

### Converting callback-style functions to await-generator style functions

### How do I start awaiting?

## Can I maintain backward compatibility?
As a wrapper, the whole `Await` can be used as a callback-async function,
and the ultimate async functions can also be callback-async functions,
but the logic between can be purely written in async/await style.
Therefore, the entry API can still be callback-async style,
and no changes are required in your library methods that accept callback-async calling.

### `yield` vs `yield from`
The straightforward approach to calling another generator function is to `yield from` that function,
but await-generator cannot distinguish the `yield` statements from the current function and the called function.
To have separate scopes for both generator functions such that state-sensitive statements like `Await::ALL` work correctly,
the generator should be yielded directly.

### Return type hints
Always add the return type hint generator functions with `Generator`.
PHP is a very "PoWeRfUl" language that automatically detects whether a function is a generator function
by searching the presence of the `yield` token in the code,
so if the developer someday removes all `yield` lines for whatever reason (e.g. behavioural changes),
the function is no longer a generator function.
To detect this kind of bugs as soon as possible (and also to allow IDEs to report errors),
always declare the `Generator` type hint.

### Empty generator function
As mentioned above, a PHP function is only a generator function when it contains a `yield` token.
But a function may still want to return a generator without having `yield` for many reasons,
such as interface implementation or API consistency.
[This StackOverflow question](https://stackoverflow.com/q/25428615/3990767)
discusses a handful of approaches to produce an empty generator.

In await-generator, for the sake of consistency,
the idiomatic way to create an immediate-return generator is to add a `false && yield;` line at the beginning of the function.
It is more concise than `if(false) yield;` (because some code styles mandate line breaks behind if statements),
and it has superstitiously better performance than `yield from [];`.
`false &&` is an obvious implication that the following line is dead code,
and is rarely used in other occasions,
so the expression `false && yield;` is idiomatic to imply "let's make sure this is a generator function".
It is reasonable to include this line even in functions that already contain other `yield` statements.

### `yield Await::ONCE`
The syntax to produce a generator from a callback function consists of two lines:

```php
callback_function(yield, yield Await::REJECT);
yield Await::ONCE;
```

To make code more concise, it is idiomatic to use the following instead:

```php
yield callback_function(yield, yield Await::REJDCT) => Await::ONCE;
```

Since await-generator ignores the yielded key for `Await::ONCE`, the following two snippets have identical effect. However, some IDEs might not like this since `callback_function()` most likely returns void and is invalid to use in the yielded key.

## Example with [libasynql](https://github.com/poggit/libasynql)
### Sequential await
> Task: Execute select query `query1`; for each result row, execute insert query `query2` with the `name` column as `name` from the previous result. Execute queries one by one; don't start the second insert query before the first insert query completes.

Without await-generator:

```php
$done = function() {
  $this->getLogger()->info("Done!");
};
$onError = function(SqlError $error) {
  $this->getLogger()->logException($error);
};
$this->connector->executeSelect("query1", [], function(array $rows) use($done, $onError) {
  $i = 0;
  $next = function() use($next, $done, $onError, &$i) {
    $this->connector->executeInsert("query2", ["name" => $rows[$i++]["name"]], isset($rows[$i]) ? $next : $done, $onError);
  };
  $next();
}, $onError);
```

With await-generator:

```php
function asyncSelect(string $query, array $args) : Generator {
  $this->connector->executeSelect($query, $args, yield, yield Await::REJECT);
  return yield Await::ONCE;
}
function asyncInsert(string $query, array $args) : Generator {
  $this->connector->executeInsert($query, $args, yield, yield Await::REJECT);
  return yield Await::ONCE;
}
```

```php
$done = function() {
  $this->getLogger()->info("Done!");
};
$onError = function(SqlError $error) {
  $this->getLogger()->logException($error);
};
Await::f2c(function() {
  $rows = yield $this->asyncSelect("query1", []);
  foreach($rows as $row) {
    yield $this->asyncInsert("query2", ["name" => $row["name"]]);
  }
}, $done, $onError);
```

Although the first example has shorter code, you can see that the looping logic (the `$next` function) is very complicated.

### Simultaneous await
> Task: same as above, except all insert queries are executed simultaneously

Without await-generator:

```php
$done = function() {
  $this->getLogger()->info("Done!");
};
$onError = function(SqlError $error) {
  $this->getLogger()->logException($error);
};
$this->connector->executeSelect("query1", [], function(array $rows) use($done, $onError) {
  $i = count($rows);
  foreach($rows as $row) {
    $this->connector->executeInsert("query2", ["name" => $row["name"]], function() use($done, &$i) {
      $i--;
      if($i === 0) $done();
    }, $onError);
  }
}, $onError);
```

With await-generator:

```php
function asyncSelect(string $query, array $args) : Generator {
  $this->connector->executeSelect($query, $args, yield, yield Await::REJECT);
  return yield Await::ONCE;
}

```

```php
$done = function() {
  $this->getLogger()->info("Done!");
};
$onError = function(SqlError $error) {
  $this->getLogger()->logException($error);
};
Await::f2c(function() {
  $rows = yield $this->asyncSelect("query1", []);
  foreach($rows as $row) {
    $this->connector->executeInsert("query2", ["name" => $row["name"]], yield, yield Await::REJECT);
  }
  yield Await::ALL;
}, $done, $onError);
```
