# await-generator
A library to use async/await in PHP using generators.

A quick comparison between a class written in traditional callback-async pattern (leading to the infamous callback hell) and one written in the await-generator pattern: [CallbackHellControl.php](tests/virion_tests/SOFe/AwaitGenerator/CallbackHellControl.php) vs [AwaitTest.php](tests/virion_tests/SOFe/AwaitGenerator/AwaitTest.php).

## Documentation
await-generator is a wrapper to convert a traditional callback-async function into async/await functions.

### Why await-generator?
The callback-async function requires passing and creating many onComplete callables throughout the code, making the
code very unreadable, known as the "callback hell". The async/await approach allows code to be written linearly and
in normal language control structures (e.g. `if`, `for`, `return`), as if the code was not written async.

### Can I maintain backward compatibility?
As a wrapper, the whole `Await` can be used as a callback-async function, and the ultimate async functions can also
be callback-async functions, but the logic between can be purely written in async/await style. Therefore, the entry
API can still be callback-async style, and no changes are required in your library methods that accept callback-async
calling.

### How to migrate to async/await pattern easily?
The following steps are recommended:
- For any function with a `callable $onComplete` parameter you want to migrate, trace up its caller stack until there
are external API methods that you can't change, or until there are no more callers that pass an `$onComplete` to
trace.
- For this "ultimate caller" function, wrap all the code in an `Await::closure()` call such that
  - the first parameter is a generator function that wraps the original code
  - the second parameter is the input `$onComplete` (if any)
- Now migrate the code in the first parameter. There are three types of statements that you need to change:
  - If it is an internal async function (something that you just traced up),
    1. change it to `yield Await::FROM => async_function()`, and remove the callable parameters in the code
    2. modify the called function's signature so that it no longer requires a callable, and returns a Generator
    3. migrate the code inside the function in the same way (no need to wrap with `Await::closure()`)
  - If it is an external async function (something that you can't change),
    1. change it to `yield Await::ASYNC => async_function(yield)`, where the second `yield` should be placed at the
place the callable should be passed
  - For both internal and external async functions, the `yield` statement can be used to receive the values returned (or normally passed to `$onComplete`) from the function.
  - For any original `$onComplete()` + `return` calls, return the args originally for `$onComplete` directly in an
array. If there is only one element, it does not need to be wrapped with an array unless it is null or an array
itself.
