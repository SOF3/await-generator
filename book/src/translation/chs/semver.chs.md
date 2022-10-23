> Versioning concerns
   * chs

版本选择的考量

***
> await\-generator is guaranteed to be
> shade\-compatible, backward\-compatible and partly forward\-compatible\.
   * chs

await-generator 保证了覆盖相容、回溯相容和部分的未来相容。

***
> Await\-generator uses generator objects for communication\.
> The values passed through generators \(such as `Await::ONCE`\)
> are constant strings that are guaranteed to remain unchanged within a major version\.
> Therefore, multiple shaded versions of await\-generator can be used together\.
   * chs

await\-generator 使用生成器物件进行沟通。
在生成器间传输的值（例如 `Await::ONCE` ）都是字符串常量，它们保证在一个主要版本中保持不变。
因此， await\-generator 的多个阴影版本可以一起使用。

***
> New constants may be added over minor versions\.
> Older versions will crash when they receive constants from newer versions\.
   * chs

新的常量可能会在次要版本中被添加。
旧版本在接收来自新版本的常量时将会崩溃。

***
> Only `Await::f2c`\/`Await::g2c` loads await\-generator code\.
> Functions that merely `yield` values from the `Await` class
> will not affect the execution logic\.
> Therefore, the version of await\-generator
> on which `Await::f2c`\/`Await::g2c` is called
> determines the highest version to use\.
   * chs

只有 `Await::f2c` 、 `Await::g2c` 会载入 await-generator 代码。
仅以 `yield` 传出 `Await` 中的常量将不会影响执行逻辑。
因此，被调用的以上两个函数所属哪个 await-generator 版本决定了在不修改原有代码下，可相容的最高版本。

***
> \(For those who do not use virion framework and are confused\:
> await\-generator is versioned just like the normal semver for you\.\)
   * chs

（对于那些不使用 Virion 框架而感到困惑的人：
请把 await-generator 看作普通的《语义化版本控制规范》一样。）

***