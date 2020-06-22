# await-generator [![Build Status](https://github.com/SOF3/await-generator/workflows/CI/badge.svg)](https://github.com/SOF3/await-generator/actions?query=workflow%3ACI) [![Codecov](https://img.shields.io/codecov/c/github/codecov/example-python.svg)](https://codecov.io/gh/SOF3/await-generator)

A library to use async/await in PHP using generators.

## Documentation
await-generator is a wrapper to convert a traditional callback-async function into async/await functions.

### Why await-generator?
The callback-async function requires passing and creating many onComplete callables throughout the code, making the code very unreadable, known as the "callback hell". The async/await approach allows code to be written linearly and in normal language control structures (e.g. `if`, `for`, `return`), as if the code was not written async.

### Can I maintain backward compatibility?
As a wrapper, the whole `Await` can be used as a callback-async function, and the ultimate async functions can also be callback-async functions, but the logic between can be purely written in async/await style. Therefore, the entry API can still be callback-async style, and no changes are required in your library methods that accept callback-async calling.

### How to migrate to async/await pattern easily?
The following steps are recommended:
- For any function with a `callable $onComplete` parameter you want to migrate (and possibly a `callable $onError`), trace up its caller stack until there are external API methods that you can't change, or until there are no more callers that pass an `$onComplete` to trace.
- For this "ultimate caller" function, wrap all the code in an `Await::f2c()` call such that
  - the first parameter is a generator function that wraps the original code
  - the second parameter is the input `$onComplete` (if any)
  - the third parameter
- Now migrate the code in the first parameter. There are three types of statements that you need to change:
  - If it is an internal async function (something that you just traced up),
    1. change it to `yield async_function()`, and remove the callable parameters in the code
    2. modify the called function's signature so that it no longer requires a callable, and returns a Generator
    3. migrate the code inside the function in the same way (no need to wrap with `Await::closure()`)
  - If it is an external async function (something that you can't change),
    - change it to `yield async_function(yield) => Await::ONCE`, where the second `yield` should be placed at the
    - if it has an error callback, pass `yield Await::REJECT` instead. Note that `yield Await::REJECT` must be resolved _after_ the empty `yield` (equivalent to `yield Await::RESOLVE`); if the error callback is required first, `Await::RESOLVE` has to be yielded in the previous statement.
- For places the callable should be passed
  - Yielding a Generator will return the return value from the Generator
  - For any original `$onComplete()` + `return` calls, return the argument originally for `$onComplete` directly in an array. Since only one value can be returned, only one value will be passed into the onComplete function, i.e. the value sent to the `yield` statement in the caller for internal calls. For external calls, i.e. where the generator is passed to `Await::f2c()` or `Await::g2c()` directly, argument expansion can be operated manually.

## Best/Idiomatic practices
### `yield` vs `yield from`
The straightforward approach to calling another generator function is to `yield from` that function, but await-generator cannot distinguish the `yield` statements from the current function and the called function. To have separate scopes for both generator functions such that state-sensitive statements like `Await::ALL` work correctly, the generator should be yielded directly.

### Return type hints
Always add the return type hint generator functions with `Generator`. PHP is a very "PoWeRfUl" language that automatically detects whether a function is a generator function by searching the presence of the `yield` token in the code, so if the developer someday removes all `yield` lines for whatever reason (e.g. behavioural changes), the function is no longer a generator function. To detect this kind of bugs as soon as possible (and also to allow IDEs to report errors), always declare the `Generator` type hint.

### Empty generator function
As mentioned above, a PHP function is only a generator function when it contains a `yield` token. But a function may still want to return a generator without having `yield` for many reasons, such as interface implementation or API consistency. [This StackOverflow question](https://stackoverflow.com/q/25428615/3990767) discusses a handful of approaches to produce an empty generator.

In await-generator, for the sake of consistency, the idiomatic way to create an immediate-return generator is to add a `false && yield;` line at the beginning of the function. It is more concise than `if(false) yield;` (because some code styles mandate line breaks behind if statements), and it has superstitiously better performance than `yield from [];`. `false &&` is an obvious implication that the following line is dead code, and is rarely used in other occasions, so the expression `false && yield;` is idiomatic to imply "let's make sure this is a generator function". It is reasonable to include this line even in functions that already contain other `yield` statements.

### `yield Await::ONCE`
The syntax to produce a generator from a callback function consists of two lines:

```php
callback_function(yield, yield Await::REJECT);
yield Await::ONCE;
```

To make code more concise, it is idiomatic to use the following instead:

```php
yield callback_function(yield, yield Await::REJECT) => Await::ONCE;
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
