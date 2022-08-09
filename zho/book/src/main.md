# Using await-generator

await-generator provides an alternative approach to asynchronous programming.
Functions that use async logic are written in generator functions.
The main trick is that your function pauses (using `yield`)
when you want to wait for a value,
then await-generator resumes your function and
sends you the return value from the async function via `$generator->send()`.
