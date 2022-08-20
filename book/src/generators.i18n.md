> Generators
   * zho

生成器

***
> A PHP function that contains a `yield` keyword is called a \"generator function\".
   * zho

一個含 `yield` 或 `yield from` 語句的函數被稱為「生成器函數」。

***
> function foo\(\) \{&#10;&#9;echo \"hi!\\n\"\;&#10;&#9;yield\;&#10;&#9;echo \"working hard\.\\n\"\;&#10;&#9;yield\;&#10;&#9;echo \"bye!\\n\"\;&#10;\}&#10;
   * zho

function foo\(\) \{&#10;&#9;echo \"hi!\\n\"\;&#10;&#9;yield\;&#10;&#9;echo \"working hard\.\\n\"\;&#10;&#9;yield\;&#10;&#9;echo \"bye!\\n\"\;&#10;\}&#10;

***
> When you call this function, it does not do anything
> \(it doesn\'t even echo \"hi\"\)\.
> Instead, you get a [`Generator`](<https://php.net/class.generator>) object,
> which lets you control the execution of the function\.
   * zho

當你調用這個函數是，它不會有任何動作，連 `hi` 也不會打印。
然而，你會得到一個 [`Generator`](<https://php.net/class.generator>) 物件，它可以被用來控制函數的代碼流。

***
> Let\'s tell PHP to start running this function\:
   * zho

讓我們告訴 PHP 去執行生成器函數的代碼：

***
> \$generator \= foo\(\)\;&#10;echo \"Let\'s start foo\\n\"\;&#10;\$generator\-\>rewind\(\)\;&#10;echo \"foo stopped\\n\"\;&#10;
   * zho

\$generator \= foo\(\)\;&#10;echo \"開始執行 foo ：\\n\"\;&#10;\$generator\-\>rewind\(\)\;&#10;echo \"foo 結束了\\n\"\;&#10;

***
> You will get this output\:
   * zho

得到以下結果：

***
> Let\'s start foo&#10;hi!&#10;foo stopped&#10;
   * zho

開始執行 foo ：&#10;hi!&#10;foo 結束了&#10;

***
> The function stops when there is a `yield` statement\.
> We can tell the function to continue running using the `Generator` object\:
   * zho

可以看出，代碼流在出現 `yield` 時暫停了。
我們可以用 `Generator` 物件來恢復代碼流：

***
> \$generator\-\>send\(null\)\;&#10;
   * zho

\$generator\-\>send\(null\)\;&#10;

***
> And this additional output\:
   * zho

這樣就又多了一行結果

***
> working hard\.&#10;
   * zho

working hard\.&#10;

***
> Now it stops again at the next `yield`.
   * zho

現在，代碼流在第二個 `yield` 暫停下來。

***
> Sending data into\/out of the `Generator`
   * zho

將數值傳入、傳出 `Generator`

***
> We can put a value behind the `yield` keyword to send data to the controller\:
   * zho

我們可以在 `yield` 關鍵字的後方設置要傳出生成器的數值：

***
> function bar\(\) \{&#10;&#9;yield 1\;&#10;\}&#10;\$generator \= bar\(\)\;&#10;\$generator\-\>rewind\(\)\;&#10;var_dump\(\$generator\-\>current\(\)\)\;&#10;
   * zho

function bar\(\) \{&#10;&#9;yield 1\;&#10;\}&#10;\$generator \= bar\(\)\;&#10;\$generator\-\>rewind\(\)\;&#10;var_dump\(\$generator\-\>current\(\)\)\;&#10;

***
> int\(1\)&#10;
   * zho

int\(1\)&#10;

***
> Similarly, we can send data back to the function\.
> If you use `yield [value]` as an expression,
> it is resolved into the value passed in `$generator->send()`\.
   * zho

同樣地，我們可以把數值傳入生成器：
如果 `yield [傳出數值]` 被用作接受數值的傳入， `$generator->send()` 的回傳結果將為它所傳出的數值。

***
> function bar\(\) \{&#10;&#9;\$receive \= yield\;&#10;&#9;var_dump\(\$receive\)\;&#10;\}&#10;\$generator \= bar\(\)\;&#10;\$generator\-\>rewind\(\)\;&#10;\$generator\-\>send\(2\)\;&#10;
   * zho

function bar\(\) \{&#10;&#9;\$receive \= yield\;&#10;&#9;var_dump\(\$receive\)\;&#10;\}&#10;\$generator \= bar\(\)\;&#10;\$generator\-\>rewind\(\)\;&#10;\$generator\-\>send\(2\)\;&#10;

***
> int\(2\)&#10;
   * zho

int\(2\)&#10;

***
> Furthermore, the function can eventually \"return\" a value\.
> This return value is not handled the same way as a `yield`\;
> it is obtained using `$generator->getReturn()`\.
> However, the return type hint must always be `Generator`
> no matter what you return, or if you don\'t return\:
   * zho

此外，生成器函數能回傳一個最終的數值。回傳跟 `yield` 的傳出不同，該數值需從 `$generator->getReturn()` 獲得。
但不管你回傳的數值屬於什麼種類，又或者你不回傳任何數值，生成器函數的回傳註譯必須為 `Generator` 。

***
> function qux\(\)\: Generator \{&#10;&#9;yield 1\;&#10;&#9;return 2\;&#10;\}&#10;
   * zho

function qux\(\)\: Generator \{&#10;&#9;yield 1\;&#10;&#9;return 2\;&#10;\}&#10;

***
> Calling another generator
   * zho

調用另外的生成器

***
> You can call another generator in a generator,
> which will pass through all the yielded values
> and send back all the sent values
> using the `yield from` syntax\.
> The `yield from` expression resolves to the return value of the generator\.
   * zho

你可以在一個生成器中調用另外的生成器，這樣在執行你的生成器時就會經過它所有的 `yield` 。
在此期間，傳入你生成器的數值會給了它，它傳出的數值也會是你生成器傳出的數值，等同於將它的代碼嵌入了你的生成器。
`yield from` 的結果將是它的回傳結果。

***
> function test\(\$value\)\: Generator \{&#10;&#9;\$send \= yield \$value\;&#10;&#9;return \$send\;&#10;\}&#10;&#10;function main\(\)\: Generator \{&#10;&#9;\$a \= yield from test\(1\)\;&#10;&#9;\$b \= yield from test\(2\)\;&#10;&#9;var\_dump\(\$a \+ \$b\)\;&#10;\}&#10;&#10;\$generator \= main\(\)\;&#10;\$generator\-\>rewind\(\)\;&#10;var\_dump\(\$generator\-\>current\(\)\)\;&#10;\$generator\-\>send\(3\)\;&#10;var\_dump\(\$generator\-\>current\(\)\)\;&#10;\$generator\-\>send\(4\)\;&#10;
   * zho

function test\(\$value\)\: Generator \{&#10;&#9;\$send \= yield \$value\;&#10;&#9;return \$send\;&#10;\}&#10;&#10;function main\(\)\: Generator \{&#10;&#9;\$a \= yield from test\(1\)\;&#10;&#9;\$b \= yield from test\(2\)\;&#10;&#9;var\_dump\(\$a \+ \$b\)\;&#10;\}&#10;&#10;\$generator \= main\(\)\;&#10;\$generator\-\>rewind\(\)\;&#10;var\_dump\(\$generator\-\>current\(\)\)\;&#10;\$generator\-\>send\(3\)\;&#10;var\_dump\(\$generator\-\>current\(\)\)\;&#10;\$generator\-\>send\(4\)\;&#10;

***
> int\(1\)&#10;int\(2\)&#10;int\(7\)&#10;
   * zho

int\(1\)&#10;int\(2\)&#10;int\(7\)&#10;

***
> Hacking generators
   * zho

假的生成器

***
> Sometimes we want to make a generator function that does not yield at all\.
> In that case, you can write `0 && yield;` at the start of the function\;
> this will make your function a generator function, but it will not yield anything\.
> As of PHP 7\.4\.0, `0 && yield;` is a no\-op,
> which means it will not affect your program performance
> even if you run this line many times\.
   * zho

我們有時候會想做一個不含 `yield` 的生成器。
那時，我們可以在普通函數的最初加上 `0 && yield;` ，使它成為一個不暫停代碼流的生成器函數。
從 PHP 7\.4\.0 起， `0 && yield;` 就是一個「no\-op」。
這意味著它不會影響你的代碼的性能，哪怕你運行它多次。

***
> function emptyGenerator\(\)\: Generator \{&#10;&#9;0 \&\& yield\;&#10;&#9;return 1\;&#10;\}&#10;&#10;\$generator \= emptyGenerator\(\)\;&#10;var\_dump\(\$generator\-\>next\(\)\)\;&#10;var\_dump\(\$generator\-\>getReturn\(\)\)\;&#10;
   * zho

function emptyGenerator\(\)\: Generator \{&#10;&#9;0 \&\& yield\;&#10;&#9;return 1\;&#10;\}&#10;&#10;\$generator \= emptyGenerator\(\)\;&#10;var\_dump\(\$generator\-\>next\(\)\)\;&#10;var\_dump\(\$generator\-\>getReturn\(\)\)\;&#10;

***
> NULL&#10;int\(1\)&#10;
   * zho

NULL&#10;int\(1\)&#10;

***