# Technical specification
## Generator yield/send interface
The following lists the behaviour of await-generator
when a generator passed to `g2c` yields a value.

The yielded key is always unrestricted.
Even non-array-key-types like `null` or `object`s can be passed,
but they are ignored.

| Yield value | Evaluates to | Behaviour |
| :---: | :---: | :---: |
| `null` | | Alias for `Await::RESOLVE` |
| `Await::RESOLVE` | A `callable` accepting exactly one argument | Indicates the start of a new callback-style async function call, returns the success callback |
| `Await::REJECT` | A `callable` accepting exactly one `Throwable` argument | Follows `Await::RESOLVE` immediately, returns the error callback |
| `[Await::RESOLVE]` | | Alias for `Await::RESOLVE_MULTI` |
| `Await::RESOLVE_MULTI` | A `callable` accepting dynamic number of arguments | Equivalent to `Await::RESOLVE`, but packs all arguments into one array |
| `Await::ONCE` | Result of the last callback-style async function call | Suspends until the last callback-style async function call resolves or rejects. Throws if `Await::RESOLVE` was called multiple times after the last suspension. |
| `Await::ALL` | An array containing the results of the callback-style async function calls since the last suspension in the same order they were called | Suspends until all callback-style async function calls after the last suspension resolves. Rejects if any of them rejects. |
| `Await::RACE` | Result of the earliest-resolved callback-style async function call since the last suspension | Suspends until any callback-style async function call after the last suspension resolves or rejects. Resolving/rejecting does not stop executing other incomplete calls. |
| A `Generator` object | The value returned by the generator | Effectively equivalent to `yield from`. |
