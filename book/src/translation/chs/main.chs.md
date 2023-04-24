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

await\-generator 提供了另类的方式进行异步编程。
含有异步动作的函数会以生成器函数的形式存在，其中的精髓是让你的函数在需要等待数值时，可以使用 `yield` 暂停代码流。
而 await\-generator 将通过 `$generator->send()` 将异步动作的结果供你的函数使用，同时恢复它的运行。

***