> Running generators concurrently
   * chs

并发生成器

***
> In addition to calling multiple generators sequentially,
> you can also use `Await::all()` or `Await::race()` to run multiple generators\.
   * chs

除了可以依次调用多个生成器，以 `Await::all()` or `Await::race()` 运行多个生成器也是不错的选择。

***
> If you have a JavaScript background, you can think of `Generator` objects as promises
> and `Await::all()` and `Await::race()` are just `Promise.all()` and `Promise.race()`\.
   * chs

如果你有 JavaScript 的背景，你可以将生成器物件想象成 promise ， 将 `Await::all()` 和 `Await::race()` 想象成 `Promise.all()` 和 `Promise.race()` 。

***
> `Await::all()`
   * chs

`Await::all()`

***
> `Await::all()` allows you to run an array of generators at the same time\.
> If you yield `Await::all($array)`, your function resumes when
> all generators in `$array` have finished executing\.
   * chs

`Await::all()` 让你可以同时运行一个阵列里的生成器。
当你 `yield from Await::all($array)` ，你的函数将在阵列 `$array` 中的生成器都完成后恢复运行。

***
> function loadData\(string \$name\)\: Generator \{&#10;&#9;\/\/ some async logic&#10;&#9;return strlen\(\$name\)\;&#10;\}&#10;&#10;\$array \= \[&#10;&#9;\"SOFe\" \=\> \$this\-\>loadData\(\"SOFe\"\), \/\/ don\'t yield it yet\!&#10;&#9;\"PEMapModder\" \=\> \$this\-\>loadData\(\"PEMapModder\"\),&#10;\]\;&#10;\$results \= yield from Await\:\:all\(\$array\)\;&#10;var_dump\(\$result\)\;&#10;
   * chs

function loadData\(string \$name\)\: Generator \{&#10;&#9;\/\/ some async logic&#10;&#9;return strlen\(\$name\)\;&#10;\}&#10;&#10;\$array \= \[&#10;&#9;\"SOFe\" \=\> \$this\-\>loadData\(\"SOFe\"\), \/\/ don\'t yield it yet\!&#10;&#9;\"PEMapModder\" \=\> \$this\-\>loadData\(\"PEMapModder\"\),&#10;\]\;&#10;\$results \= yield from Await\:\:all\(\$array\)\;&#10;var_dump\(\$result\)\;&#10;

***
> Output\:
   * chs

「 `loadData()` 包含某些异步逻辑」；
「在此先别 `yield from` 」。
结果：

***
> array\(2\) \{&#10;  \[\"SOFe\"\]\=\>&#10;  int\(4\)&#10;  \[\"PEMapModder\"\]\=\>&#10;  int\(11\)&#10;\}&#10;
   * chs

array\(2\) \{&#10;  \[\"SOFe\"\]\=\>&#10;  int\(4\)&#10;  \[\"PEMapModder\"\]\=\>&#10;  int\(11\)&#10;\}&#10;

***
> Yielding `Await::all()` will throw an exception
> as long as _any_ of the generators throw\.
> The error condition will not wait until all generators return\.
   * chs

`yield from Await::all()` 会在任一生成器异常时立即进行抛出，并不会等待所有生成器回传。

***
> `Await::race()`
   * chs

`Await::race()`

***
> `Await::race()` is like `Await::all()`,
> but it resumes as long as _any_ of the generators return or throw\.
> The returned value of `yield from` is a 2\-element array containing the key and the value\.
   * chs

`Await::race()` 跟 `Await::all()` 差不多，
但当任一生成器回传或抛出异常时就会恢复你函数的运行。
`yield from` 结果为一个包含键与值的双项目阵列。
「假设这是『等待式异步』版的 `scheduleDelayedTask` 」。

***
> function sleep\(int \$time\)\: Generator \{&#10;&#9;\/\/ Let\'s say this is an await version of \`scheduleDelayedTask\`&#10;&#9;return \$time\;&#10;\}&#10;&#10;function main\(\)\: Generator \{&#10;&#9;\[\$k, \$v\] \= yield from Await\:\:race\(\[&#10;&#9;&#9;\"two\" \=\> \$this\-\>sleep\(2\),&#10;&#9;&#9;\"one\" \=\> \$this\-\>sleep\(1\),&#10;&#9;\]\)\;&#10;&#9;var\_dump\(\$k\)\; \/\/ string\(3\) \"one\"&#10;&#9;var\_dump\(\$v\)\; \/\/ int\(1\)&#10;\}&#10;
   * chs

function sleep\(int \$time\)\: Generator \{&#10;&#9;\/\/ Let\'s say this is an await version of \`scheduleDelayedTask\`&#10;&#9;return \$time\;&#10;\}&#10;&#10;function main\(\)\: Generator \{&#10;&#9;\[\$k, \$v\] \= yield from Await\:\:race\(\[&#10;&#9;&#9;\"two\" \=\> \$this\-\>sleep\(2\),&#10;&#9;&#9;\"one\" \=\> \$this\-\>sleep\(1\),&#10;&#9;\]\)\;&#10;&#9;var\_dump\(\$k\)\; \/\/ string\(3\) \"one\"&#10;&#9;var\_dump\(\$v\)\; \/\/ int\(1\)&#10;\}&#10;

***