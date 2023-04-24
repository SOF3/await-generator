> Using await\-generator
   * zho

使用 await–generator

***
> await\-generator provides an alternative approach to asynchronous programming\.
> Functions that use async logic are written in generator functions\.
> The main trick is that your function pauses \(using `yield`\)
> when you want to wait for a value,
> then await\-generator resumes your function and
> sends you the return value from the async function via `$generator->send()`\.
   * zho

await\-generator 提供了另類的方式進行異步編程。
含有異步動作的函數會以生成器函數的形式存在，其中的精髓是讓你的函數在需要等待數值時，可以使用 `yield` 暫停代碼流。
而 await\-generator 將通過 `$generator->send()` 將異步動作的結果供你的函數使用，同時恢復它的運行。

***