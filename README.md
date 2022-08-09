Eng | [繁](zho) | [简](chs)
# await-generator
[![Build Status][ci-badge]][ci-page]
[![Codecov][codecov-badge]][codecov-page]

A library to use async/await pattern in PHP.

## Documentation
Read the [await-generator tutorial][book] for an introduction
from generators and traditional async callbacks to await-generator.

## Why await-generator?
Traditional async programming requires callbacks,
which leads to spaghetti code known as "callback hell":
<details>
    <summary>Click to reveal example callback hell</summary>
    
```php
load_data(function($data) {
    $init = count($data) === 0 ? init_data(...) : fn($then) => $then($data);
    $init(function($data) {
        $output = [];
        foreach($data as $k => $datum) {
            processData($datum, function($result) use(&$output, $data) {
                $output[$k] = $result;
                if(count($output) === count($data)) {
                    createQueries($output, function($queries) {
                        $run = function($i) use($queries, &$run) {
                            runQuery($queries[$i], function() use($i, $queries, $run) {
                                if($i === count($queries)) {
                                    $done = false;
                                    commitBatch(function() use(&$done) {
                                        if(!$done) {
                                            $done = true;
                                            echo "Done!\n";
                                        }
                                    });
                                    onUserClose(function() use(&$done) {
                                        if(!$done) {
                                            $done = true;
                                            echo "User closed!\n";
                                        }
                                    });
                                    onTimeout(function() use(&$done) {
                                        if(!$done) {
                                            $done = true;
                                            echo "Timeout!\n";
                                        }
                                    });
                                } else {
                                    $run($i + 1);
                                }
                            });
                        };
                    });
                }
            });
        }
    });
});
```
    
</details>
With await-generator, this is simplified into:

```php
$data = yield from load_data();
if(count($data) === 0) $data = yield from init_data();
$output = yield from Await::all(array_map(fn($datum) => processData($datum), $data));
$queries = yield from createQueries($output);
foreach($queries as $query) yield from runQuery($query);
[$which, ] = yield from Await::race([
    0 => commitBatch(),
    1 => onUserClose(),
    2 => onTimeout(),
])
echo match($which) {
    0 => "Done!\n",
    1 => "User closed!\n",
    2 => "Timeout!\n",
};
```

## Can I maintain backward compatibility?
Yes, await-generator does not impose any restrictions on your existing API.
You can wrap all await-generator calls as internal implementation detail,
although you are strongly encouraged to expose the generator functions directly.

await-generator starts an await context with the `Await::f2c` method,
with which you can adapt into the usual callback syntax:

```php
function oldApi($args, Closure $onSuccess) {
    Await::f2c(fn() => $onSuccess(yield from newApi($args)));
}
```

Or if you want to handle errors too:

```php
function newApi($args, Closure $onSuccess, Closure $onError) {
    Await::f2c(function() use($onSuccess, $onError) {
        try {
            $onSuccess(yield from newApi($args));
        } catch(Exception $ex) {
            $onError($ex);
        }
    });
}
```

You can continue to call functions implemented as callback style
using the `Await::promise` method (similar to `new Promise` in JS):

```php
yield from Await::promise(fn($resolve, $reject) => oldFunction($args, $resolve, $reject));
```

## Why *not* await-generator
await-generator has a few common pitfalls:

- Forgetting to `yield from` a `Generator<void>` method will end up doing nothing.
- If you delete all `yield`s from a function,
  it automatically becomes a non-generator function thanks to PHP magic.
  This issue can be mitigated by always adding `: Generator` to the function signature.
- `finally` blocks may never get executed if an async function never resolves
  (e.g. `Await::promise(fn($resolve) => null)`).

While these pitfalls cause some trouble,
await-generator style is still much less bug-prone than a callback hell.

## But what about fibers?
This might be a subjective comment,
but I do not prefer fibers for a few reasons:

### Explicit suspension in type signature
![fiber.jpg](./fiber.jpeg)

For example, it is easy to tell from the type signature that
`$channel->send($value): Generator<void>` suspends until the value is sent
and `$channel->sendBuffered($value): void`
is a non-suspending method that returns immediately.
Type signatures are often self-explanatory.

Of course, users could call `sleep()` anyway,
but it is quite obvious to everyone that `sleep()` blocks the whole runtime
(if they didn't already know, they will find out when the whole world stops).

### Concurrent states
When a function suspends, many other things can happen.
Indeed, calling a function allows the implementation to call any other functions
which could modify your states anyway,
but a sane, genuine implementation of e.g. an HTTP request
wouldn't call functions that modify the private states of your library.
But this assumption does not hold with fibers
because the fiber is preempted and other fibers can still modify the private states.
This means you have to check for possible changes in private properties
every time you call any function that *might* be suspending.

On the other hand, using explicit await,
it is obvious where exactly the suspension points are,
and you only need to check for state mutations at the known suspension points.

### Trapping suspension points
await-generator provides a feature called ["trapping"][trap-pr],
which allows users to add pre-suspend and pre-resume hooks to a generator.
This is simply achieved by adding an adapter to the generator,
and does not even require explicit support from the await-generator runtime.
This is currently not possible with fibers.

[book]: https://sof3.github.io/await-generator/master/
[ci-badge]: https://github.com/SOF3/await-generator/workflows/CI/badge.svg
[ci-page]: https://github.com/SOF3/await-generator/actions?query=workflow%3ACI
[codecov-badge]: https://img.shields.io/codecov/c/github/codecov/example-python.svg
[codecov-page]: https://codecov.io/gh/SOF3/await-generator
[trap-pr]: https://github.com/SOF3/await-generator/pull/106
