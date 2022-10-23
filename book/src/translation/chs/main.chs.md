> Using await\-generator
   * chs

使用 await–generator

***
> await\-generator provides an alternative approach to asynchronous programming\.
> Functions that use async logic are written in generator functions\.
> The main trick is that your function pauses \(using `yield`\)
> when you want to wait for a value,
> then await\-generator resumes your function and
> sends you the return value from the async function via `$generator->send()`\.
   * chs

await\-generator 提供了一种另类编写异步代码的方式。
含有异步动作的函数会以生成器函数的形式存在。
最重要的一点是你的函数可以在需要等待数值时被暂停（使用 `yield`）。
而 await\-generator 通过 `$generator->send()` 将异步函数的回传值供你的函数使用，同时恢复它的运行。

***