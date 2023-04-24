> Generators
   * chs

生成器

***
> A PHP function that contains a `yield` keyword is called a \"generator function\".
   * chs

一个含 `yield` 或 `yield from` 语句的函数被称为「生成器函数」。

***
> function foo\(\) \{&#10;&#9;echo \"hi!\\n\"\;&#10;&#9;yield\;&#10;&#9;echo \"working hard\.\\n\"\;&#10;&#9;yield\;&#10;&#9;echo \"bye!\\n\"\;&#10;\}&#10;
   * chs

function foo\(\) \{&#10;&#9;echo \"hi!\\n\"\;&#10;&#9;yield\;&#10;&#9;echo \"working hard\.\\n\"\;&#10;&#9;yield\;&#10;&#9;echo \"bye!\\n\"\;&#10;\}&#10;

***
> When you call this function, it does not do anything
> \(it doesn\'t even echo \"hi\"\)\.
> Instead, you get a [`Generator`](<https://php.net/class.generator>) object,
> which lets you control the execution of the function\.
   * chs

当你调用这个函数是，它不会有任何动作，连 `hi` 也不会显示。
然则，你会得到一个生成器物件[（ `Generator`） ](<https://php.net/class.generator>) ，它可以被用来控制函数的代码流。

***
> Let\'s tell PHP to start running this function\:
   * chs

让我们告诉 PHP 去执行生成器函数的代码：

***
> \$generator \= foo\(\)\;&#10;echo \"Let\'s start foo\\n\"\;&#10;\$generator\-\>rewind\(\)\;&#10;echo \"foo stopped\\n\"\;&#10;
   * chs

\$generator \= foo\(\)\;&#10;echo \"开始执行 foo ：\\n\"\;&#10;\$generator\-\>rewind\(\)\;&#10;echo \"foo 结束了\\n\"\;&#10;

***
> You will get this output\:
   * chs

「开始执行 `foo` 」；
「 `foo` 结束了」。
得到以下结果：

***
> Let\'s start foo&#10;hi!&#10;foo stopped&#10;
   * chs

开始执行 foo ：&#10;hi!&#10;foo 结束了&#10;

***
> The function stops when there is a `yield` statement\.
> We can tell the function to continue running using the `Generator` object\:
   * chs

由此可见，函数在出现 `yield` 时暂停了。
我们可以透过 `$generator` 中的生成器物件恢复运行：

***
> \$generator\-\>send\(null\)\;&#10;
   * chs

\$generator\-\>send\(null\)\;&#10;

***
> And this additional output\:
   * chs

这样就又多了一行结果：

***
> working hard\.&#10;
   * chs

working hard\.&#10;

***
> Now it stops again at the next `yield`.
   * chs

现在，它在第二个 `yield` 暂停下来。

***
> Sending data into\/out of the `Generator`
   * chs

将数值传入、传出生成器

***
> We can put a value behind the `yield` keyword to send data to the controller\:
   * chs

我们可以在 `yield` 关键词的后方设置要传出生成器的数值：

***
> function bar\(\) \{&#10;&#9;yield 1\;&#10;\}&#10;\$generator \= bar\(\)\;&#10;\$generator\-\>rewind\(\)\;&#10;var_dump\(\$generator\-\>current\(\)\)\;&#10;
   * chs

function bar\(\) \{&#10;&#9;yield 1\;&#10;\}&#10;\$generator \= bar\(\)\;&#10;\$generator\-\>rewind\(\)\;&#10;var_dump\(\$generator\-\>current\(\)\)\;&#10;

***
> int\(1\)&#10;
   * chs

int\(1\)&#10;

***
> Similarly, we can send data back to the function\.
> If you use `yield [value]` as an expression,
> it is resolved into the value passed in `$generator->send()`\.
   * chs

同样地，我们可以把数值传入生成器（如果 `yield [要传出的数值]` 被以赋值语句的形式使用，它就能接收 `$generator->send()` 传入的数值）：

***
> function bar\(\) \{&#10;&#9;\$receive \= yield\;&#10;&#9;var_dump\(\$receive\)\;&#10;\}&#10;\$generator \= bar\(\)\;&#10;\$generator\-\>rewind\(\)\;&#10;\$generator\-\>send\(2\)\;&#10;
   * chs

function bar\(\) \{&#10;&#9;\$receive \= yield\;&#10;&#9;var_dump\(\$receive\)\;&#10;\}&#10;\$generator \= bar\(\)\;&#10;\$generator\-\>rewind\(\)\;&#10;\$generator\-\>send\(2\)\;&#10;

***
> int\(2\)&#10;
   * chs

int\(2\)&#10;

***
> Furthermore, the function can eventually \"return\" a value\.
> This return value is not handled the same way as a `yield`\;
> it is obtained using `$generator->getReturn()`\.
> However, the return type hint must always be `Generator`
> no matter what you return, or if you don\'t return\:
   * chs

此外，生成器函数能回传一个最终的数值。回传跟 `yield` 的传出不同，该数值需从 `$generator->getReturn()` 获得。
但不管你回传的数值属于什么种类，又或者你不回传任何数值，生成器函数的回传注译必须为 `Generator` 。

***
> function qux\(\)\: Generator \{&#10;&#9;yield 1\;&#10;&#9;return 2\;&#10;\}&#10;
   * chs

function qux\(\)\: Generator \{&#10;&#9;yield 1\;&#10;&#9;return 2\;&#10;\}&#10;

***
> Calling another generator
   * chs

调用另外的生成器

***
> You can call another generator in a generator,
> which will pass through all the yielded values
> and send back all the sent values
> using the `yield from` syntax\.
> The `yield from` expression resolves to the return value of the generator\.
   * chs

你可以在一个生成器中调用另外的生成器，这样在执行你的生成器时就会经过它所有的 `yield` 。
在此期间，传入你生成器的数值会转让予它，它传出的数值也会成为你生成器传出的数值，等同于将它的代码嵌入了你的生成器。
赋值语句 `yield from` 的结果将是它的回传结果。

***
> function test\(\$value\)\: Generator \{&#10;&#9;\$send \= yield \$value\;&#10;&#9;return \$send\;&#10;\}&#10;&#10;function main\(\)\: Generator \{&#10;&#9;\$a \= yield from test\(1\)\;&#10;&#9;\$b \= yield from test\(2\)\;&#10;&#9;var\_dump\(\$a \+ \$b\)\;&#10;\}&#10;&#10;\$generator \= main\(\)\;&#10;\$generator\-\>rewind\(\)\;&#10;var\_dump\(\$generator\-\>current\(\)\)\;&#10;\$generator\-\>send\(3\)\;&#10;var\_dump\(\$generator\-\>current\(\)\)\;&#10;\$generator\-\>send\(4\)\;&#10;
   * chs

function test\(\$value\)\: Generator \{&#10;&#9;\$send \= yield \$value\;&#10;&#9;return \$send\;&#10;\}&#10;&#10;function main\(\)\: Generator \{&#10;&#9;\$a \= yield from test\(1\)\;&#10;&#9;\$b \= yield from test\(2\)\;&#10;&#9;var\_dump\(\$a \+ \$b\)\;&#10;\}&#10;&#10;\$generator \= main\(\)\;&#10;\$generator\-\>rewind\(\)\;&#10;var\_dump\(\$generator\-\>current\(\)\)\;&#10;\$generator\-\>send\(3\)\;&#10;var\_dump\(\$generator\-\>current\(\)\)\;&#10;\$generator\-\>send\(4\)\;&#10;

***
> int\(1\)&#10;int\(2\)&#10;int\(7\)&#10;
   * chs

int\(1\)&#10;int\(2\)&#10;int\(7\)&#10;

***
> Hacking generators
   * chs

假的生成器

***
> Sometimes we want to make a generator function that does not yield at all\.
> In that case, you can write `0 && yield;` at the start of the function\;
> this will make your function a generator function, but it will not yield anything\.
> As of PHP 7\.4\.0, `0 && yield;` is a no\-op,
> which means it will not affect your program performance
> even if you run this line many times\.
   * chs

我们有时候会想做一个不含 `yield` 的生成器。
那时，我们可以在普通函数的最初加上 `0 && yield;` ，使它成为一个同步的的生成器函数。
从 PHP 7\.4\.0 起， `0 && yield;` 就是一个「no\-op」。
这意味着它不会影响你的代码的性能，哪怕你运行它多次。

***
> function emptyGenerator\(\)\: Generator \{&#10;&#9;0 \&\& yield\;&#10;&#9;return 1\;&#10;\}&#10;&#10;\$generator \= emptyGenerator\(\)\;&#10;var\_dump\(\$generator\-\>next\(\)\)\;&#10;var\_dump\(\$generator\-\>getReturn\(\)\)\;&#10;
   * chs

function emptyGenerator\(\)\: Generator \{&#10;&#9;0 \&\& yield\;&#10;&#9;return 1\;&#10;\}&#10;&#10;\$generator \= emptyGenerator\(\)\;&#10;var\_dump\(\$generator\-\>next\(\)\)\;&#10;var\_dump\(\$generator\-\>getReturn\(\)\)\;&#10;

***
> NULL&#10;int\(1\)&#10;
   * chs

NULL&#10;int\(1\)&#10;

***