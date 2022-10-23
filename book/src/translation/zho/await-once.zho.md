> Using callback\-style from generators
   * zho

生成器中的「回調式」

***
> Although it is easier to work with generator functions,
> ultimately, you will need to work with functions that do not use await\-generator\.
> In that case, callbacks are easier to use\.
> A callback `$resolve` can be acquired using `Await::promise`\.
   * zho

儘管生成器函數更容易使用，你終究還可能需要用到非 await\-generator 使用者的函數。
面對他們，回調更容易使用。
一個回調（ `$resolve` ）可以從 `Await::promise` 獲得。

***
> function a\(Closure \$callback\)\: void \{&#10;&#9;\/\/ The other function that uses callbacks\.&#10;&#9;\/\/ Let\'s assume this function will call \$callback\(\"foo\"\) some time later\.&#10;\}&#10;&#10;function main\(\)\: Generator \{&#10;&#9;return yield from Await\:\:promise\(fn\(\$resolve\) \=\> a\(\$resolve\)\)\;&#10;\}&#10;
   * zho

function a\(Closure \$callback\)\: void \{&#10;&#9;\/\/ The other function that uses callbacks\.&#10;&#9;\/\/ Let\'s assume this function will call \$callback\(\"foo\"\) some time later\.&#10;\}&#10;&#10;function main\(\)\: Generator \{&#10;&#9;return yield from Await\:\:promise\(fn\(\$resolve\) \=\> a\(\$resolve\)\)\;&#10;\}&#10;

***
> Some callback\-style async functions may accept another callback for exception handling. This callback can be acquired by taking a second parameter `$reject`.
   * zho

「 `a()` 是某個使用回調的函數」；
「假設它會在某個時刻調用 `$callback("foo")` 」。
有些「回調式」異步函數還會接受一個額外的回調來處理異常。
這樣的回調可以從第二個參數（ `$reject` ）獲得。

***
> function a\(Closure \$callback, Closure \$onError\)\: void \{&#10;&#9;\/\/ The other function that uses callbacks\.&#10;&#9;\/\/ Let\'s assume this function will call \$callback\(\"foo\"\) some time later\.&#10;\}&#10;&#10;function main\(\)\: Generator \{&#10;&#9;return yield from Await\:\:promise\(fn\(\$resolve, \$reject\) \=\> a\(\$resolve, \$reject\)\)\;&#10;\}&#10;
   * zho

function a\(Closure \$callback, Closure \$onError\)\: void \{&#10;&#9;\/\/ The other function that uses callbacks\.&#10;&#9;\/\/ Let\'s assume this function will call \$callback\(\"foo\"\) some time later\.&#10;\}&#10;&#10;function main\(\)\: Generator \{&#10;&#9;return yield from Await\:\:promise\(fn\(\$resolve, \$reject\) \=\> a\(\$resolve, \$reject\)\)\;&#10;\}&#10;

***
> Example
   * zho

栗子 🌰

***
> Let\'s say we want to make a function that sleeps for 20 server ticks,
> or throws an exception if the task is cancelled\:
   * zho

讓我們製作一個暫停 20 個伺服器刻的函數，它會在任務（ `$task` ）取消時拋出異常：

***
> use pocketmine\\scheduler\\Task\;&#10;&#10;public function sleep\(\)\: Generator \{&#10;&#9;yield from Await\:\:promise\(function\(\$resolve, \$reject\) \{&#10;&#9;&#9;\$task \= new class\(\$resolve, \$reject\) extends Task \{&#10;&#9;&#9;&#9;private \$resolve\;&#10;&#9;&#9;&#9;private \$reject\;&#10;&#9;&#9;&#9;public function \_\_construct\(\$resolve, \$reject\) \{&#10;&#9;&#9;&#9;&#9;\$this\-\>resolve \= \$resolve\;&#10;&#9;&#9;&#9;&#9;\$this\-\>reject \= \$reject\;&#10;&#9;&#9;&#9;\}&#10;&#9;&#9;&#9;public function onRun\(int \$tick\) \{&#10;&#9;&#9;&#9;&#9;\(\$this\-\>resolve\)\(\)\;&#10;&#9;&#9;&#9;\}&#10;&#9;&#9;&#9;public function onCancel\(\) \{&#10;&#9;&#9;&#9;&#9;\(\$this\-\>reject\)\(new \\Exception\(\"Task cancelled\"\)\)\;&#10;&#9;&#9;&#9;\}&#10;&#9;&#9;\}\;&#10;&#9;&#9;\$this\-\>getServer\(\)\-\>getScheduler\(\)\-\>scheduleDelayedTask\(\$task, 20\)\;&#10;&#9;\}\)\;&#10;\}&#10;
   * zho

use pocketmine\\scheduler\\Task\;&#10;&#10;public function sleep\(\)\: Generator \{&#10;&#9;yield from Await\:\:promise\(function\(\$resolve, \$reject\) \{&#10;&#9;&#9;\$task \= new class\(\$resolve, \$reject\) extends Task \{&#10;&#9;&#9;&#9;private \$resolve\;&#10;&#9;&#9;&#9;private \$reject\;&#10;&#9;&#9;&#9;public function \_\_construct\(\$resolve, \$reject\) \{&#10;&#9;&#9;&#9;&#9;\$this\-\>resolve \= \$resolve\;&#10;&#9;&#9;&#9;&#9;\$this\-\>reject \= \$reject\;&#10;&#9;&#9;&#9;\}&#10;&#9;&#9;&#9;public function onRun\(int \$tick\) \{&#10;&#9;&#9;&#9;&#9;\(\$this\-\>resolve\)\(\)\;&#10;&#9;&#9;&#9;\}&#10;&#9;&#9;&#9;public function onCancel\(\) \{&#10;&#9;&#9;&#9;&#9;\(\$this\-\>reject\)\(new \\Exception\(\"Task cancelled\"\)\)\;&#10;&#9;&#9;&#9;\}&#10;&#9;&#9;\}\;&#10;&#9;&#9;\$this\-\>getServer\(\)\-\>getScheduler\(\)\-\>scheduleDelayedTask\(\$task, 20\)\;&#10;&#9;\}\)\;&#10;\}&#10;

***
> This is a bit complex indeed, but it gets handy once we have this function defined!
> Let\'s see what we can do with a countdown\:
   * zho

「 `new \Exception("任務取消")` 」。
這確實有點複雜，但一旦我們定義了這個函數，它以後就會方便我們使用了！
讓我們看看它可以怎麼被應用到倒計時中：

***
> function countdown\(\$player\) \{&#10;&#9;for\(\$i \= 10\; \$i \> 0\; \$i\-\-\) \{&#10;&#9;&#9;\$player\-\>sendMessage\(\"\$i seconds left\"\)\;&#10;&#9;&#9;yield from \$this\-\>sleep\(\)\;&#10;&#9;\}&#10;&#10;&#9;\$player\-\>sendMessage\(\"Time\'s up!\"\)\;&#10;\}&#10;
   * zho

function countdown\(\$player\) \{&#10;&#9;for\(\$i \= 10\; \$i \> 0\; \$i\-\-\) \{&#10;&#9;&#9;\$player\-\>sendMessage\(\"\$i seconds left\"\)\;&#10;&#9;&#9;yield from \$this\-\>sleep\(\)\;&#10;&#9;\}&#10;&#10;&#9;\$player\-\>sendMessage\(\"Time\'s up!\"\)\;&#10;\}&#10;

***
> This is much simpler than using `ClosureTask` in a loop!
   * zho

「剩下 `$i` 秒」；
「倒計時結束！」。
這樣比 `ClosureTask` 迴圈簡單得多！

***
