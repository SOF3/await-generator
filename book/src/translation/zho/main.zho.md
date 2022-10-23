> Using await\-generator
   * zho

使用 await\-generator

***
> await\-generator provides an alternative approach to asynchronous programming\.
> Functions that use async logic are written in generator functions\.
> The main trick is that your function pauses \(using `yield`\)
> when you want to wait for a value,
> then await\-generator resumes your function and
> sends you the return value from the async function via `$generator->send()`\.
   * zho

await\-generator 提供了一種另類編寫異步代碼的方式。
含有異步動作的函數會以生成器函數的形式存在。
最重要的一點是你的函數可以在需要等待數值時被暫停（使用 `yield`）。
而 await\-generator 通過 `$generator->send()` 將異步函數的回傳值供你的函數使用，同時恢復它的運行。

***