> Asynchronous programming
   * chs

异步编程

***
> Traditionally, when you call a function,
> it performs the required actions and returns after they\'re done\.
> In asynchronous programming,
> the program logic may be executed _after_ a function returns\.
   * chs

一般情况下，函数被调用时会执行动作，直到结束或回传数值。
但当涉及异步编程，大多数动作会在函数结束或回传后才被执行。

***
> This leads to two problems\.
> First, the function can\'t return you with any useful results,
> because the results are only available after the logic completes\.
> Second, you may do something else assuming the logic is completed,
> which leads to a bug\.
> For example\:
   * chs

这样就会导致两个问题：
一、动作所生产的结果赶不上函数的回传，因此这些函数基本不能回传任何有用的结果；
二、动作的进度未知，却被假设为完成，造成 bug 隐患。

***
> private \$data\;&#10;&#10;function loadData\(\$player\) \{&#10;&#9;\/\/ we will set \$this\-\>data\[\$player\] some time later\.&#10;\}&#10;&#10;function main\(\) \{&#10;&#9;\$this\-\>loadData\(\"SOFe\"\)\;&#10;&#9;echo \$this\-\>data\[\"SOFe\"\]\; \/\/ Undefined offset \"SOFe\"&#10;\}&#10;
   * chs

private \$data\;&#10;&#10;function loadData\(\$player\) \{&#10;&#9;\/\/ 假设 \$this\-\>data\[\$player\] 在一段时间后才被赋值。&#10;\}&#10;&#10;function main\(\) \{&#10;&#9;\$this\-\>loadData\(\"SOFe\"\)\;&#10;&#9;echo \$this\-\>data\[\"SOFe\"\]\; \/\/ Undefined offset \"SOFe\" （未赋值错误）&#10;\}&#10;

***
> Here, `loadData` is the function that loads data asynchronously\.
> `main` is implemented incorrectly, assuming that `loadData` is synchronous,
> i\.e\. it assumes that `$this->data["SOFe"]` is initialized\.
   * chs

「// `$this->data[$player]` 在一段时间后才被赋值」；
「// （未赋值错误）」。
`loadData` 异步地载入资料。
但 `main` 却误以为它是同步的，且在结束前会为 `$this->data["SOFe"]` 赋值。

***
> Using callbacks
   * chs

使用回调

***
> One of the simplest ways to solve this problem is to use callbacks\.
> The caller can pass a closure to the async function,
> then the async function will run this closure when it has finished\.
> An example function signature would be like this\:
   * chs

以上问题最简单直接的解决方法是使用「callback 回调」。
函数调用者可传递回调，而它在完成异步动作后再调用该回调。
请参考例子中函数的样式：

***
> function loadData\(\$player, Closure \$callback\) \{&#10;&#9;\/\/ \$callback will be called when player data have been loaded\.&#10;\}&#10;&#10;function main\(\) \{&#10;&#9;\$this\-\>loadData\(\"SOFe\", function\(\) \{&#10;&#9;&#9;echo \$this\-\>data\[\"SOFe\"\]\; \/\/ this is guaranteed to work now&#10;&#9;\}\)\;&#10;\}&#10;
   * chs

function loadData\(\$player, Closure \$callback\) \{&#10;&#9;\/\/ \$callback 会在玩家（$player）资料完成载入后调用。&#10;\}&#10;&#10;function main\(\) \{&#10;&#9;\$this\-\>loadData\(\"SOFe\", function\(\) \{&#10;&#9;&#9;echo \$this\-\>data\[\"SOFe\"\]\; \/\/ 现在就能确保它已被赋值&#10;&#9;\}\)\;&#10;\}&#10;

***
> The `$callback` will be called when some other logic happens\.
> This depends on the implementation of the `loadData` logic\.
> This may be when a player sends a certain packet,
> or when a scheduled task gets run,
> or other scenarios\.
   * chs

「// `$callback` 会在资料完成载入后被调用」；
「// 现在就能保证它已被赋值」。
`$callback` 的调用视乎 `loadData` 之逻辑的实现。
例如当接收到特定的封包、排程任务被执行。

***
> More complex callbacks
   * chs

更复杂的回调

***
> \(This section is deliberately complicated and hard to understand,
> because the purpose is to tell you that using callbacks is bad\.\)
   * chs

（此部分刻意以复杂的形式书写，目的是要强调回调有多糟糕。）

***
> What if we want to call multiple async functions one by one?
> In synchronous code, it would be simple\:
   * chs

如果我们想依次调用函数呢？
这在同步编程流就很简单：

***
> \$a \= a\(\)\;&#10;\$b \= b\(\$a\)\;&#10;\$c \= c\(\$b\)\;&#10;\$d \= d\(\$c\)\;&#10;var_dump\(\$d\)\;&#10;
   * chs

\$a \= a\(\)\;&#10;\$b \= b\(\$a\)\;&#10;\$c \= c\(\$b\)\;&#10;\$d \= d\(\$c\)\;&#10;var_dump\(\$d\)\;&#10;

***
> In async code, we might need to do this \(let\'s say `a`, `b`, `c`, `d` are async\)\:
   * chs

但到了异步编程，我们则需要这样（以下 `a`、`b`、`c`、`d` 为异步函数）：

***
> a\(function\(\$a\) \{&#10;&#9;b\(\$a, function\(\$b\) \{&#10;&#9;&#9;c\(\$b, function\(\$c\) \{&#10;&#9;&#9;&#9;d\(\$c, function\(\$d\) \{&#10;&#9;&#9;&#9;&#9;var_dump\(\$d\)\;&#10;&#9;&#9;&#9;\}\)\;&#10;&#9;&#9;\}\)\;&#10;&#9;\}\)\;&#10;\}\)\;&#10;
   * chs

a\(function\(\$a\) \{&#10;&#9;b\(\$a, function\(\$b\) \{&#10;&#9;&#9;c\(\$b, function\(\$c\) \{&#10;&#9;&#9;&#9;d\(\$c, function\(\$d\) \{&#10;&#9;&#9;&#9;&#9;var_dump\(\$d\)\;&#10;&#9;&#9;&#9;\}\)\;&#10;&#9;&#9;\}\)\;&#10;&#9;\}\)\;&#10;\}\)\;&#10;

***
> Looks ugly, but readable enough\.
> It might look more confusing if we need to pass `$a` to `$d` though\.
   * chs

尽管代码很丑，它现仍是可阅读的。
但如果我们继续以这种方式编写，把 `$a` 带进 `$d` 后只会更加混乱。

***
> But what if we want to do if\/else?
> In synchronous code, it looks like this\:
   * chs

那如果我们想用 if、else 呢？
同步编程会是这样：

***
> \$a \= a\(\)\;&#10;if\(\$a !\=\= null\) \{&#10;&#9;\$output \= b\(\$a\)\;&#10;\} else \{&#10;&#9;\$output \= c\(\) \+ 1\;&#10;\}&#10;&#10;\$d \= very\_complex\_code\(\$output\)\;&#10;\$e \= that\_deals\_with\(\$output\)\;&#10;var\_dump\(\$d \+ \$e \+ \$a\)\;&#10;
   * chs

\$a \= a\(\)\;&#10;if\(\$a !\=\= null\) \{&#10;&#9;\$output \= b\(\$a\)\;&#10;\} else \{&#10;&#9;\$output \= c\(\) \+ 1\;&#10;\}&#10;&#10;\$d \= very\_complex\_code\(\$output\)\;&#10;\$e \= that\_deals\_with\(\$output\)\;&#10;var\_dump\(\$d \+ \$e \+ \$a\)\;&#10;

***
> In async code, it is much more confusing\:
   * chs

到了异步编程就变得扑索迷离了：

***
> a\(function\(\$a\) \{&#10;&#9;if\(\$a !\=\= null\) \{&#10;&#9;&#9;b\(\$a, function\(\$output\) use\(\$a\) \{&#10;&#9;&#9;&#9;&#9;\$d \= very\_complex\_code\(\$output\)\;&#10;&#9;&#9;&#9;&#9;\$e \= that\_deals\_with\(\$output\)\;&#10;&#9;&#9;&#9;&#9;var\_dump\(\$d \+ \$e \+ \$a\)\;&#10;&#9;&#9;\}\)\;&#10;&#9;\} else \{&#10;&#9;&#9;c\(function\(\$output\) use\(\$a\) \{&#10;&#9;&#9;&#9;&#9;\$output \= \$output \+ 1\;&#10;&#9;&#9;&#9;&#9;\$d \= very\_complex\_code\(\$output\)\;&#10;&#9;&#9;&#9;&#9;\$e \= that\_deals\_with\(\$output\)\;&#10;&#9;&#9;&#9;&#9;var\_dump\(\$d \+ \$e \+ \$a\)\;&#10;&#9;&#9;\}\)\;&#10;&#9;\}&#10;\}\)\;&#10;
   * chs

a\(function\(\$a\) \{&#10;&#9;if\(\$a !\=\= null\) \{&#10;&#9;&#9;b\(\$a, function\(\$output\) use\(\$a\) \{&#10;&#9;&#9;&#9;&#9;\$d \= very\_complex\_code\(\$output\)\;&#10;&#9;&#9;&#9;&#9;\$e \= that\_deals\_with\(\$output\)\;&#10;&#9;&#9;&#9;&#9;var\_dump\(\$d \+ \$e \+ \$a\)\;&#10;&#9;&#9;\}\)\;&#10;&#9;\} else \{&#10;&#9;&#9;c\(function\(\$output\) use\(\$a\) \{&#10;&#9;&#9;&#9;&#9;\$output \= \$output \+ 1\;&#10;&#9;&#9;&#9;&#9;\$d \= very\_complex\_code\(\$output\)\;&#10;&#9;&#9;&#9;&#9;\$e \= that\_deals\_with\(\$output\)\;&#10;&#9;&#9;&#9;&#9;var\_dump\(\$d \+ \$e \+ \$a\)\;&#10;&#9;&#9;\}\)\;&#10;&#9;\}&#10;\}\)\;&#10;

***
> But we don\'t want to copy\-paste the three lines of duplicated code\.
> Maybe we can assign the whole closure to a variable\:
   * chs

要避免以上三行代码的重复，我们需要将回调预先储存在变数中：

***
> a\(function\(\$a\) \{&#10;&#9;\$closure \= function\(\$output\) use\(\$a\) \{&#10;&#9;&#9;\$d \= very\_complex\_code\(\$output\)\;&#10;&#9;&#9;\$e \= that\_deals\_with\(\$output\)\;&#10;&#9;&#9;var\_dump\(\$d \+ \$e \+ \$a\)\;&#10;&#9;\}\;&#10;&#10;&#9;if\(\$a !\=\= null\) \{&#10;&#9;&#9;b\(\$a, \$closure\)\;&#10;&#9;\} else \{&#10;&#9;&#9;c\(function\(\$output\) use\(\$closure\) \{&#10;&#9;&#9;&#9;\$closure\(\$output \+ 1\)\;&#10;&#9;&#9;\}\)\;&#10;&#9;\}&#10;\}\)\;&#10;
   * chs

a\(function\(\$a\) \{&#10;&#9;\$closure \= function\(\$output\) use\(\$a\) \{&#10;&#9;&#9;\$d \= very\_complex\_code\(\$output\)\;&#10;&#9;&#9;\$e \= that\_deals\_with\(\$output\)\;&#10;&#9;&#9;var\_dump\(\$d \+ \$e \+ \$a\)\;&#10;&#9;\}\;&#10;&#10;&#9;if\(\$a !\=\= null\) \{&#10;&#9;&#9;b\(\$a, \$closure\)\;&#10;&#9;\} else \{&#10;&#9;&#9;c\(function\(\$output\) use\(\$closure\) \{&#10;&#9;&#9;&#9;\$closure\(\$output \+ 1\)\;&#10;&#9;&#9;\}\)\;&#10;&#9;\}&#10;\}\)\;&#10;

***
> Oh no, this is getting out of control\.
> Think about how complicated this would become when
> we want to use asynchronous functions in loops!
   * chs

不！这代码已经逐渐失去掌控了。
想想看，到循环与异步函数结合的地步时，这些天书会长成什么样子？

***
> The await\-generator library allows users to write async code in synchronous style\.
> As you might have guessed, the `yield` keyword is a replacement for callbacks\.
   * chs

await\-generator 程序库让使用者能够将异步代码以同步代码的风格表达。
也许你已经猜到， `yield` 这个语句将用来取代回调。

***
