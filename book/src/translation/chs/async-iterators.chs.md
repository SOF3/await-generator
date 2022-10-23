> Async iterators
   * chs

异步迭代器

***
> In normal PHP functions, there is only a single return value\.
> If we want to return data progressively,
> generators should have been used,
> where the user can iterate on the returned generator\.
> However, if the user intends to perform async operations
> in every step of progressive data fetching,
> the `next()` method needs to be async too\.
> In other languages, this is called \"async generator\" or \"async iterator\"\.
> However, since await\-generator has hijacked the generator syntax,
> it is not possible to create such structures directly\.
   * chs

常规的 PHP 函数只允许一个回传值。
如果我们想分阶段回传资料，生成器就该被使用。
在这里，用户可以对（生成器函数）回传的生成器进行迭代。
然而，如果用户打算在其中的每一步执行异步操作， `next()` 就必须是异步的。
这在其他程序语言中被称为「异步生成器」或「异步迭代器」。
然而，由于 await\-generator 限制了生成器的语法。
此种结构无法被直接创建。

***
> Instead, await\-generator exposes the `Traverser` class,
> which is an extension to the normal await\-generator syntax,
> providing an additional yield mode `Traverser::VALUE`,
> which allows an async function to yield async iteration values\.
> A key \(the current traversed value\) is passed with `Traverser::VALUE`\.
> The resultant generator is wrapped with the `Traverser` class,
> which provides an asynchronous `next()` method that
> executes the generator asynchronously and returns the next traversed value,
   * chs

不过， await\-generator 有自己的遍历器。
它延伸了一般的 await\-generator 语法，提供了一个额外的 `yield` 模式（ `Traverser::VALUE` ），让异步函数能同时作为迭代器，异步地传出迭代值。
在这种模式下，键（要传出的迭代值）与 `Traverser::VALUE` 需一并递给 `yield` ；且需将生成器包裹在遍历器中，它会提供了一个异步的 `next()` 来执行被包裹的生成器，并返回下一个遍历的值。

***
> Example
   * chs

栗子 🌰

***
> In normal PHP, we may have an line iterator on a file stream like this\:
   * chs

在常规的 PHP，我们可以写出像这样的代码，来迭代档案串流中的每一行：

***
> function lines\(string \$file\) \: Generator \{&#10;&#9;\$fh \= fopen\(\$file, \"rt\"\)\;&#10;&#9;try \{&#10;&#9;&#9;while\(\(\$line \= fgets\(\$fh\)\) !\=\= false\) \{&#10;&#9;&#9;&#9;yield \$line\;&#10;&#9;&#9;\}&#10;&#9;\} finally \{&#10;&#9;&#9;fclose\(\$fh\)\;&#10;&#9;\}&#10;\}&#10;&#10;function count\_empty\_lines\(string \$file\) \{&#10;&#9;\$count \= 0\;&#10;&#9;foreach\(lines\(\$file\) as \$line\) \{&#10;&#9;&#9;if\(trim\(\$line\) \=\=\= \"\"\) \$count\+\+\;&#10;&#9;\}&#10;&#9;return \$count\;&#10;\}&#10;
   * chs

function lines\(string \$file\) \: Generator \{&#10;&#9;\$fh \= fopen\(\$file, \"rt\"\)\;&#10;&#9;try \{&#10;&#9;&#9;while\(\(\$line \= fgets\(\$fh\)\) !\=\= false\) \{&#10;&#9;&#9;&#9;yield \$line\;&#10;&#9;&#9;\}&#10;&#9;\} finally \{&#10;&#9;&#9;fclose\(\$fh\)\;&#10;&#9;\}&#10;\}&#10;&#10;function count\_empty\_lines\(string \$file\) \{&#10;&#9;\$count \= 0\;&#10;&#9;foreach\(lines\(\$file\) as \$line\) \{&#10;&#9;&#9;if\(trim\(\$line\) \=\=\= \"\"\) \$count\+\+\;&#10;&#9;\}&#10;&#9;return \$count\;&#10;\}&#10;

***
> What if we have async versions of `fopen`, `fgets` and `fclose`
> and want to reimplement this `lines` function as async?
   * chs

但如果我们有异步的 `fopen` 、 `fgets` 和 `fclose` ，并需要重写 `lines` 函数以实现异步呢？

***
> We would use the `Traverser` class instead\:
   * chs

我们将使用迭代器：

***
> function async\_lines\(string \$file\) \: Generator \{&#10;&#9;\$fh \= yield from async\_fopen\(\$file, \"rt\"\)\;&#10;&#9;try \{&#10;&#9;&#9;while\(true\) \{&#10;&#9;&#9;&#9;\$line \= yield from async\_fgets\(\$fh\)\;&#10;&#9;&#9;&#9;if\(\$line \=\=\= false\) \{&#10;&#9;&#9;&#9;&#9;return\;&#10;&#9;&#9;&#9;\}&#10;&#9;&#9;&#9;yield \$line \=\> Await\:\:VALUE\;&#10;&#9;&#9;\}&#10;&#9;\} finally \{&#10;&#9;&#9;yield from async\_fclose\(\$fh\)\;&#10;&#9;\}&#10;\}&#10;&#10;function async\_count\_empty\_lines\(string \$file\) \: Generator \{&#10;&#9;\$count \= 0\;&#10;&#10;&#9;\$traverser \= new Traverser\(async\_lines\(\$file\)\)\;&#10;&#9;while\(yield from \$traverser\-\>next\(\$line\)\) \{&#10;&#9;&#9;if\(trim\(\$line\) \=\=\= \"\"\) \$count\+\+\;&#10;&#9;\}&#10;&#10;&#9;return \$count\;&#10;\}&#10;
   * chs

function async\_lines\(string \$file\) \: Generator \{&#10;&#9;\$fh \= yield from async\_fopen\(\$file, \"rt\"\)\;&#10;&#9;try \{&#10;&#9;&#9;while\(true\) \{&#10;&#9;&#9;&#9;\$line \= yield from async\_fgets\(\$fh\)\;&#10;&#9;&#9;&#9;if\(\$line \=\=\= false\) \{&#10;&#9;&#9;&#9;&#9;return\;&#10;&#9;&#9;&#9;\}&#10;&#9;&#9;&#9;yield \$line \=\> Await\:\:VALUE\;&#10;&#9;&#9;\}&#10;&#9;\} finally \{&#10;&#9;&#9;yield from async\_fclose\(\$fh\)\;&#10;&#9;\}&#10;\}&#10;&#10;function async\_count\_empty\_lines\(string \$file\) \: Generator \{&#10;&#9;\$count \= 0\;&#10;&#10;&#9;\$traverser \= new Traverser\(async\_lines\(\$file\)\)\;&#10;&#9;while\(yield from \$traverser\-\>next\(\$line\)\) \{&#10;&#9;&#9;if\(trim\(\$line\) \=\=\= \"\"\) \$count\+\+\;&#10;&#9;\}&#10;&#10;&#9;return \$count\;&#10;\}&#10;

***
> Interrupting a generator
   * chs

中断迭代器

***
> Yielding inside `finally` may cause a crash
> if the generator is not yielded fully\.
> If you perform async operations in the `finally` block,
> you __must__ drain the traverser fully\.
> If you don\'t want the iterator to continue executing,
> you may use the `yield $traverser->interrupt()` method,
> which keeps throwing the first parameter
> \(`SOFe\AwaitGenerator\InterruptException` by default\)
> into the async iterator until it stops executing\.
> Beware that `interrupt` may throw an `AwaitException`
> if the underlying generator catches exceptions during `yield Traverser::VALUE`s
> \(hence consuming the interrupts\)\.
   * chs

如果一个生成器的所有 `yield` 没被完全消耗，在 `finally` 中进行 `yield` 可能会导致崩溃。
因此在 `finally` 中进行异步操作时，你 __必须__ 把遍历器完全消耗。
若你希望中断它。
你可以使用 `yield $traverser->interrupt()` ，不断抛出参数中的异常（ 默认为 `SOFe\AwaitGenerator\InterruptException` ）至遍历器包裹的迭代器，直到它停止执行。
请注意，如果底层的某个生成器捕捉到这个异常（因而吞噬了中断的信号）， `interrupt` 就可能会抛出 `AwaitException`。

***
> It is not necessary to interrupt the traverser
> if there are no `finally` blocks containing `yield` statements\.
   * chs

如果 `finally` 中不含任何 `yield` ，则无须主动中断。

***