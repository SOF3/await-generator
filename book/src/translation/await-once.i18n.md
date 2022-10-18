> Using callback\-style from generators
   * zho

轉接「等待式」至「回調式」

***
> Although it is easier to work with generator functions,
> ultimately, you will need to work with functions that do not use await\-generator\.
> In that case, callbacks are easier to use\.
> This is achieved by `Await::promise`\.
   * zho

儘管生成器函數更容易使用，將來你卻還是會用到非 await\-generator 的函數。
在這種情況下，回調更容易使用。
這是由`Await::promise`實現的。

***
> function a\(Closure \$callback\)\: void \{&#10;&#9;\/\/ The other function that uses callbacks\.&#10;&#9;\/\/ Let\'s assume this function will call \$callback\(\"foo\"\) some time later\.&#10;\}&#10;&#10;function main\(\)\: Generator \{&#10;&#9;return yield from Await\:\:promise\(fn\(\$resolve\) \=\> a\(\$resolve\)\)\;&#10;\}&#10;
   * zho

function a\(Closure \$callback\)\: void \{&#10;&#9;\/\/ The other function that uses callbacks\.&#10;&#9;\/\/ Let\'s assume this function will call \$callback\(\"foo\"\) some time later\.&#10;\}&#10;&#10;function main\(\)\: Generator \{&#10;&#9;return yield from Await\:\:promise\(fn\(\$resolve\) \=\> a\(\$resolve\)\)\;&#10;\}&#10;

***
> Some callback\-style async functions may also accept an `$onError` callback parameter\.
> This callback can be created by calling `Await::REJECT`\.
> Then `Await::ONCE` will call your function 
   * zho

Some callback\-style async functions may also accept an `$onError` callback parameter\.
This callback can be created by calling `Await::REJECT`\.
Then `Await::ONCE` will call your function 

***
> function a\(Closure \$callback, Closure \$onError\)\: void \{&#10;&#9;\/\/ The other function that uses callbacks\.&#10;&#9;\/\/ Let\'s assume this function will call \$callback\(\"foo\"\) some time later\.&#10;\}&#10;&#10;function main\(\)\: Generator \{&#10;&#9;return yield from Await\:\:promise\(fn\(\$resolve, \$reject\) \=\> a\(\$resolve, \$reject\)\)\;&#10;\}&#10;
   * zho

function a\(Closure \$callback, Closure \$onError\)\: void \{&#10;&#9;\/\/ The other function that uses callbacks\.&#10;&#9;\/\/ Let\'s assume this function will call \$callback\(\"foo\"\) some time later\.&#10;\}&#10;&#10;function main\(\)\: Generator \{&#10;&#9;return yield from Await\:\:promise\(fn\(\$resolve, \$reject\) \=\> a\(\$resolve, \$reject\)\)\;&#10;\}&#10;

***
> Example
   * zho

Example

***
> Let\'s say we want to make a function that sleeps for 20 server ticks,
> or throws an exception if the task is cancelled\:
   * zho

Let\'s say we want to make a function that sleeps for 20 server ticks,
or throws an exception if the task is cancelled\:

***
> use pocketmine\\scheduler\\Task\;&#10;&#10;public function sleep\(\)\: Generator \{&#10;&#9;yield from Await\:\:promise\(function\(\$resolve, \$reject\) \{&#10;&#9;&#9;\$task \= new class\(\$resolve, \$reject\) extends Task \{&#10;&#9;&#9;&#9;private \$resolve\;&#10;&#9;&#9;&#9;private \$reject\;&#10;&#9;&#9;&#9;public function \_\_construct\(\$resolve, \$reject\) \{&#10;&#9;&#9;&#9;&#9;\$this\-\>resolve \= \$resolve\;&#10;&#9;&#9;&#9;&#9;\$this\-\>reject \= \$reject\;&#10;&#9;&#9;&#9;\}&#10;&#9;&#9;&#9;public function onRun\(int \$tick\) \{&#10;&#9;&#9;&#9;&#9;\(\$this\-\>resolve\)\(\)\;&#10;&#9;&#9;&#9;\}&#10;&#9;&#9;&#9;public function onCancel\(\) \{&#10;&#9;&#9;&#9;&#9;\(\$this\-\>reject\)\(new \\Exception\(\"Task cancelled\"\)\)\;&#10;&#9;&#9;&#9;\}&#10;&#9;&#9;\}\;&#10;&#9;&#9;\$this\-\>getServer\(\)\-\>getScheduler\(\)\-\>scheduleDelayedTask\(\$task, 20\)\;&#10;&#9;\}\)\;&#10;\}&#10;
   * zho

use pocketmine\\scheduler\\Task\;&#10;&#10;public function sleep\(\)\: Generator \{&#10;&#9;yield from Await\:\:promise\(function\(\$resolve, \$reject\) \{&#10;&#9;&#9;\$task \= new class\(\$resolve, \$reject\) extends Task \{&#10;&#9;&#9;&#9;private \$resolve\;&#10;&#9;&#9;&#9;private \$reject\;&#10;&#9;&#9;&#9;public function \_\_construct\(\$resolve, \$reject\) \{&#10;&#9;&#9;&#9;&#9;\$this\-\>resolve \= \$resolve\;&#10;&#9;&#9;&#9;&#9;\$this\-\>reject \= \$reject\;&#10;&#9;&#9;&#9;\}&#10;&#9;&#9;&#9;public function onRun\(int \$tick\) \{&#10;&#9;&#9;&#9;&#9;\(\$this\-\>resolve\)\(\)\;&#10;&#9;&#9;&#9;\}&#10;&#9;&#9;&#9;public function onCancel\(\) \{&#10;&#9;&#9;&#9;&#9;\(\$this\-\>reject\)\(new \\Exception\(\"Task cancelled\"\)\)\;&#10;&#9;&#9;&#9;\}&#10;&#9;&#9;\}\;&#10;&#9;&#9;\$this\-\>getServer\(\)\-\>getScheduler\(\)\-\>scheduleDelayedTask\(\$task, 20\)\;&#10;&#9;\}\)\;&#10;\}&#10;

***
> This is a bit complex indeed, but it gets handy once we have this function defined!
> Let\'s see what we can do with a countdown\:
   * zho

This is a bit complex indeed, but it gets handy once we have this function defined!
Let\'s see what we can do with a countdown\:

***
> function countdown\(\$player\) \{&#10;&#9;for\(\$i \= 10\; \$i \> 0\; \$i\-\-\) \{&#10;&#9;&#9;\$player\-\>sendMessage\(\"\$i seconds left\"\)\;&#10;&#9;&#9;yield from \$this\-\>sleep\(\)\;&#10;&#9;\}&#10;&#10;&#9;\$player\-\>sendMessage\(\"Time\'s up!\"\)\;&#10;\}&#10;
   * zho

function countdown\(\$player\) \{&#10;&#9;for\(\$i \= 10\; \$i \> 0\; \$i\-\-\) \{&#10;&#9;&#9;\$player\-\>sendMessage\(\"\$i seconds left\"\)\;&#10;&#9;&#9;yield from \$this\-\>sleep\(\)\;&#10;&#9;\}&#10;&#10;&#9;\$player\-\>sendMessage\(\"Time\'s up!\"\)\;&#10;\}&#10;

***
> This is much simpler than using `ClosureTask` in a loop!
   * zho

This is much simpler than using `ClosureTask` in a loop!

***
