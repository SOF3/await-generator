> Versioning concerns
   * zho

版本選擇的考量

***
> await\-generator is guaranteed to be
> shade\-compatible, backward\-compatible and partly forward\-compatible\.
   * zho

await-generator 保證了覆蓋相容、回溯相容和部分的未來相容。

***
> Await\-generator uses generator objects for communication\.
> The values passed through generators \(such as `Await::ONCE`\)
> are constant strings that are guaranteed to remain unchanged within a major version\.
> Therefore, multiple shaded versions of await\-generator can be used together\.
   * zho

await\-generator 使用生成器物件進行溝通。
在生成器間傳輸的值（例如 `Await::ONCE` ）都是字符串常量，它們保證在一個主要版本中保持不變。
因此， await\-generator 的多個陰影版本可以一起使用。

***
> New constants may be added over minor versions\.
> Older versions will crash when they receive constants from newer versions\.
   * zho

新的常量可能會在次要版本中被添加。
舊版本在接收來自新版本的常量時將會崩潰。

***
> Only `Await::f2c`\/`Await::g2c` loads await\-generator code\.
> Functions that merely `yield` values from the `Await` class
> will not affect the execution logic\.
> Therefore, the version of await\-generator
> on which `Await::f2c`\/`Await::g2c` is called
> determines the highest version to use\.
   * zho

只有 `Await::f2c` 、 `Await::g2c` 會載入 await-generator 代碼。
僅以 `yield` 傳出 `Await` 中的常量將不會影響執行邏輯。
因此，被調用的以上兩個函數所屬哪個 await-generator 版本決定了在不修改原有代碼下，可相容的最高版本。

***
> \(For those who do not use virion framework and are confused\:
> await\-generator is versioned just like the normal semver for you\.\)
   * zho

（對於那些不使用 Virion 框架而感到困惑的人：
請把 await-generator 看作普通的《語義化版本控制規範》一樣。）

***