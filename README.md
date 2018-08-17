# await-generator [![Build Status](https://travis-ci.org/SOF3/await-generator.svg?branch=master)](https://travis-ci.org/SOF3/await-generator) ![Codecov](https://img.shields.io/codecov/c/github/codecov/example-python.svg)

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

### `yield` vs `yield from`
The straightforward approach to calling another generator function is to `yield from` that function, but await-generator cannot distinguish the `yield` statements from the current function and the called function. To have separate scopes for both generator functions such that state-sensitive statements like `Await::ALL` work correctly, the generator should be yielded directly.
