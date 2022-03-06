# await-generator [![Build Status](https://github.com/SOF3/await-generator/workflows/CI/badge.svg)](https://github.com/SOF3/await-generator/actions?query=workflow%3ACI) [![Codecov](https://img.shields.io/codecov/c/github/codecov/example-python.svg)](https://codecov.io/gh/SOF3/await-generator)

A library to use async/await in PHP using generators.

Read the [await-generator book](https://sof3.github.io/await-generator/master) for a thorough tutorial.

## Why await-generator?
The callback-async function requires passing and creating many onComplete callables throughout the code, making the code very unreadable, known as the "callback hell". The async/await approach allows code to be written linearly and in normal language control structures (e.g. `if`, `for`, `return`), as if the code was not written async.

An example of callback hell vs await-generator:
![](https://media.discordapp.net/attachments/373199722573201410/807112614747963412/unknown.png?width=1386&height=573)

## Documentation
await-generator is a wrapper to convert a traditional callback-async function into async/await functions.

### Can I maintain backward compatibility?
As a wrapper, the whole `Await` can be used as a callback-async function, and the ultimate async functions can also be callback-async functions, but the logic between can be purely written in async/await style. Therefore, the entry API can still be callback-async style, and no changes are required in your library methods that accept callback-async calling.

However, you are advised to expose a Generator-based API. This allows other modules to call your async code without using a troublesome callback-async style every time, which defeats the point of using await-generator.
