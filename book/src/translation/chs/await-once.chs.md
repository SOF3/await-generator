> Using callback\-style from generators
   * chs

生成器中的「回调式」

***
> Although it is easier to work with generator functions,
> ultimately, you will need to work with functions that do not use await\-generator\.
> In that case, callbacks are easier to use\.
> A callback `$resolve` can be acquired using `Await::promise`\.
   * chs

尽管生成器函数更容易使用，你终究还可能需要用到非 await\-generator 使用者的函数。
面对他们，回调更容易使用。
一个回调（ `$resolve` ）可以从 `Await::promise` 获得。

***
> function a\(Closure \$callback\)\: void \{&#10;&#9;\/\/ The other function that uses callbacks\.&#10;&#9;\/\/ Let\'s assume this function will call \$callback\(\"foo\"\) some time later\.&#10;\}&#10;&#10;function main\(\)\: Generator \{&#10;&#9;return yield from Await\:\:promise\(fn\(\$resolve\) \=\> a\(\$resolve\)\)\;&#10;\}&#10;
   * chs

function a\(Closure \$callback\)\: void \{&#10;&#9;\/\/ The other function that uses callbacks\.&#10;&#9;\/\/ Let\'s assume this function will call \$callback\(\"foo\"\) some time later\.&#10;\}&#10;&#10;function main\(\)\: Generator \{&#10;&#9;return yield from Await\:\:promise\(fn\(\$resolve\) \=\> a\(\$resolve\)\)\;&#10;\}&#10;

***
> Some callback\-style async functions may accept another callback for exception handling. This callback can be acquired by taking a second parameter `$reject`.
   * chs

「 `a()` 是某个使用回调的函数」；
「假设它会在某个时刻调用 `$callback("foo")` 」。
有些「回调式」异步函数还会接受一个额外的回调来处理异常。
这样的回调可以从第二个参数（ `$reject` ）获得。

***
> function a\(Closure \$callback, Closure \$onError\)\: void \{&#10;&#9;\/\/ The other function that uses callbacks\.&#10;&#9;\/\/ Let\'s assume this function will call \$callback\(\"foo\"\) some time later\.&#10;\}&#10;&#10;function main\(\)\: Generator \{&#10;&#9;return yield from Await\:\:promise\(fn\(\$resolve, \$reject\) \=\> a\(\$resolve, \$reject\)\)\;&#10;\}&#10;
   * chs

function a\(Closure \$callback, Closure \$onError\)\: void \{&#10;&#9;\/\/ The other function that uses callbacks\.&#10;&#9;\/\/ Let\'s assume this function will call \$callback\(\"foo\"\) some time later\.&#10;\}&#10;&#10;function main\(\)\: Generator \{&#10;&#9;return yield from Await\:\:promise\(fn\(\$resolve, \$reject\) \=\> a\(\$resolve, \$reject\)\)\;&#10;\}&#10;

***
> Example
   * chs

栗子 🌰

***
> Let\'s say we want to make a function that sleeps for 20 server ticks,
> or throws an exception if the task is cancelled\:
   * chs

让我们制作一个暂停 20 个服务器刻的函数，它会在任务（ `$task` ）取消时抛出异常：

***
> use pocketmine\\scheduler\\Task\;&#10;&#10;public function sleep\(\)\: Generator \{&#10;&#9;yield from Await\:\:promise\(function\(\$resolve, \$reject\) \{&#10;&#9;&#9;\$task \= new class\(\$resolve, \$reject\) extends Task \{&#10;&#9;&#9;&#9;private \$resolve\;&#10;&#9;&#9;&#9;private \$reject\;&#10;&#9;&#9;&#9;public function \_\_construct\(\$resolve, \$reject\) \{&#10;&#9;&#9;&#9;&#9;\$this\-\>resolve \= \$resolve\;&#10;&#9;&#9;&#9;&#9;\$this\-\>reject \= \$reject\;&#10;&#9;&#9;&#9;\}&#10;&#9;&#9;&#9;public function onRun\(int \$tick\) \{&#10;&#9;&#9;&#9;&#9;\(\$this\-\>resolve\)\(\)\;&#10;&#9;&#9;&#9;\}&#10;&#9;&#9;&#9;public function onCancel\(\) \{&#10;&#9;&#9;&#9;&#9;\(\$this\-\>reject\)\(new \\Exception\(\"Task cancelled\"\)\)\;&#10;&#9;&#9;&#9;\}&#10;&#9;&#9;\}\;&#10;&#9;&#9;\$this\-\>getServer\(\)\-\>getScheduler\(\)\-\>scheduleDelayedTask\(\$task, 20\)\;&#10;&#9;\}\)\;&#10;\}&#10;
   * chs

use pocketmine\\scheduler\\Task\;&#10;&#10;public function sleep\(\)\: Generator \{&#10;&#9;yield from Await\:\:promise\(function\(\$resolve, \$reject\) \{&#10;&#9;&#9;\$task \= new class\(\$resolve, \$reject\) extends Task \{&#10;&#9;&#9;&#9;private \$resolve\;&#10;&#9;&#9;&#9;private \$reject\;&#10;&#9;&#9;&#9;public function \_\_construct\(\$resolve, \$reject\) \{&#10;&#9;&#9;&#9;&#9;\$this\-\>resolve \= \$resolve\;&#10;&#9;&#9;&#9;&#9;\$this\-\>reject \= \$reject\;&#10;&#9;&#9;&#9;\}&#10;&#9;&#9;&#9;public function onRun\(int \$tick\) \{&#10;&#9;&#9;&#9;&#9;\(\$this\-\>resolve\)\(\)\;&#10;&#9;&#9;&#9;\}&#10;&#9;&#9;&#9;public function onCancel\(\) \{&#10;&#9;&#9;&#9;&#9;\(\$this\-\>reject\)\(new \\Exception\(\"Task cancelled\"\)\)\;&#10;&#9;&#9;&#9;\}&#10;&#9;&#9;\}\;&#10;&#9;&#9;\$this\-\>getServer\(\)\-\>getScheduler\(\)\-\>scheduleDelayedTask\(\$task, 20\)\;&#10;&#9;\}\)\;&#10;\}&#10;

***
> This is a bit complex indeed, but it gets handy once we have this function defined!
> Let\'s see what we can do with a countdown\:
   * chs

「 `new \Exception("任务取消")` 」。
这确实有点复杂，但一旦我们定义了这个函数，它以后就会方便我们使用了！
让我们看看它可以怎么被应用到倒计时中：

***
> function countdown\(\$player\) \{&#10;&#9;for\(\$i \= 10\; \$i \> 0\; \$i\-\-\) \{&#10;&#9;&#9;\$player\-\>sendMessage\(\"\$i seconds left\"\)\;&#10;&#9;&#9;yield from \$this\-\>sleep\(\)\;&#10;&#9;\}&#10;&#10;&#9;\$player\-\>sendMessage\(\"Time\'s up!\"\)\;&#10;\}&#10;
   * chs

function countdown\(\$player\) \{&#10;&#9;for\(\$i \= 10\; \$i \> 0\; \$i\-\-\) \{&#10;&#9;&#9;\$player\-\>sendMessage\(\"\$i seconds left\"\)\;&#10;&#9;&#9;yield from \$this\-\>sleep\(\)\;&#10;&#9;\}&#10;&#10;&#9;\$player\-\>sendMessage\(\"Time\'s up!\"\)\;&#10;\}&#10;

***
> This is much simpler than using `ClosureTask` in a loop!
   * chs

「剩下 `$i` 秒」；
「倒计时结束！」。
这样比 `ClosureTask` 循环简单得多！

***
