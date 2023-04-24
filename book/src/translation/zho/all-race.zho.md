> Running generators concurrently
   * zho

並發生成器

***
> In addition to calling multiple generators sequentially,
> you can also use `Await::all()` or `Await::race()` to run multiple generators\.
   * zho

除了可以依次調用多個生成器，以 `Await::all()` or `Await::race()` 運行多個生成器也是不錯的選擇。

***
> If you have a JavaScript background, you can think of `Generator` objects as promises
> and `Await::all()` and `Await::race()` are just `Promise.all()` and `Promise.race()`\.
   * zho

如果你有 JavaScript 的背景，你可以將生成器物件想像成 promise ， 將 `Await::all()` 和 `Await::race()` 想像成 `Promise.all()` 和 `Promise.race()` 。

***
> `Await::all()`
   * zho

`Await::all()`

***
> `Await::all()` allows you to run an array of generators at the same time\.
> If you yield `Await::all($array)`, your function resumes when
> all generators in `$array` have finished executing\.
   * zho

`Await::all()` 讓你可以同時運行一個陣列裏的生成器。
當你 `yield from Await::all($array)` ，你的函數將在陣列 `$array` 中的生成器都完成後恢復運行。

***
> function loadData\(string \$name\)\: Generator \{&#10;&#9;\/\/ some async logic&#10;&#9;return strlen\(\$name\)\;&#10;\}&#10;&#10;\$array \= \[&#10;&#9;\"SOFe\" \=\> \$this\-\>loadData\(\"SOFe\"\), \/\/ don\'t yield it yet\!&#10;&#9;\"PEMapModder\" \=\> \$this\-\>loadData\(\"PEMapModder\"\),&#10;\]\;&#10;\$results \= yield from Await\:\:all\(\$array\)\;&#10;var_dump\(\$result\)\;&#10;
   * zho

function loadData\(string \$name\)\: Generator \{&#10;&#9;\/\/ some async logic&#10;&#9;return strlen\(\$name\)\;&#10;\}&#10;&#10;\$array \= \[&#10;&#9;\"SOFe\" \=\> \$this\-\>loadData\(\"SOFe\"\), \/\/ don\'t yield it yet\!&#10;&#9;\"PEMapModder\" \=\> \$this\-\>loadData\(\"PEMapModder\"\),&#10;\]\;&#10;\$results \= yield from Await\:\:all\(\$array\)\;&#10;var_dump\(\$result\)\;&#10;

***
> Output\:
   * zho

「 `loadData()` 包含某些異步邏輯」；
「在此先別 `yield from` 」。
結果：

***
> array\(2\) \{&#10;  \[\"SOFe\"\]\=\>&#10;  int\(4\)&#10;  \[\"PEMapModder\"\]\=\>&#10;  int\(11\)&#10;\}&#10;
   * zho

array\(2\) \{&#10;  \[\"SOFe\"\]\=\>&#10;  int\(4\)&#10;  \[\"PEMapModder\"\]\=\>&#10;  int\(11\)&#10;\}&#10;

***
> Yielding `Await::all()` will throw an exception
> as long as _any_ of the generators throw\.
> The error condition will not wait until all generators return\.
   * zho

`yield from Await::all()` 會在任一生成器異常時立即進行拋出，並不會等待所有生成器回傳。

***
> `Await::race()`
   * zho

`Await::race()`

***
> `Await::race()` is like `Await::all()`,
> but it resumes as long as _any_ of the generators return or throw\.
> The returned value of `yield from` is a 2\-element array containing the key and the value\.
   * zho

`Await::race()` 跟 `Await::all()` 差不多，
但當任一生成器回傳或拋出異常時就會恢復你函數的運行。
`yield from` 結果為一個包含鍵與值的雙項目陣列。
「假設這是『等待式異步』版的 `scheduleDelayedTask` 」。

***
> function sleep\(int \$time\)\: Generator \{&#10;&#9;\/\/ Let\'s say this is an await version of \`scheduleDelayedTask\`&#10;&#9;return \$time\;&#10;\}&#10;&#10;function main\(\)\: Generator \{&#10;&#9;\[\$k, \$v\] \= yield from Await\:\:race\(\[&#10;&#9;&#9;\"two\" \=\> \$this\-\>sleep\(2\),&#10;&#9;&#9;\"one\" \=\> \$this\-\>sleep\(1\),&#10;&#9;\]\)\;&#10;&#9;var\_dump\(\$k\)\; \/\/ string\(3\) \"one\"&#10;&#9;var\_dump\(\$v\)\; \/\/ int\(1\)&#10;\}&#10;
   * zho

function sleep\(int \$time\)\: Generator \{&#10;&#9;\/\/ Let\'s say this is an await version of \`scheduleDelayedTask\`&#10;&#9;return \$time\;&#10;\}&#10;&#10;function main\(\)\: Generator \{&#10;&#9;\[\$k, \$v\] \= yield from Await\:\:race\(\[&#10;&#9;&#9;\"two\" \=\> \$this\-\>sleep\(2\),&#10;&#9;&#9;\"one\" \=\> \$this\-\>sleep\(1\),&#10;&#9;\]\)\;&#10;&#9;var\_dump\(\$k\)\; \/\/ string\(3\) \"one\"&#10;&#9;var\_dump\(\$v\)\; \/\/ int\(1\)&#10;\}&#10;

***