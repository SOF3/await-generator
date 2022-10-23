> Async iterators
   * zho

異步疊代器

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
   * zho

常規的 PHP 函數只允許一個回傳值。
如果我們想分階段回傳資料，生成器就該被使用。
在這裏，用戶可以對（生成器函數）回傳的生成器進行疊代。
然而，如果用戶打算在其中的每一步執行異步操作， `next()` 就必須是異步的。
這在其他程式語言中被稱為「異步生成器」或「異步疊代器」。
然而，由於 await\-generator 限制了生成器的語法。
此種結構無法被直接創建。

***
> Instead, await\-generator exposes the `Traverser` class,
> which is an extension to the normal await\-generator syntax,
> providing an additional yield mode `Traverser::VALUE`,
> which allows an async function to yield async iteration values\.
> A key \(the current traversed value\) is passed with `Traverser::VALUE`\.
> The resultant generator is wrapped with the `Traverser` class,
> which provides an asynchronous `next()` method that
> executes the generator asynchronously and returns the next traversed value,
   * zho

不過， await\-generator 有自己的遍歷器。
它延伸了一般的 await\-generator 語法，提供了一個額外的 `yield` 模式（ `Traverser::VALUE` ），讓異步函數能同時作為疊代器，異步地傳出疊代值。
在這種模式下，鍵（要傳出的疊代值）與 `Traverser::VALUE` 需一併遞給 `yield` ；且需將生成器包裹在遍歷器中，它會提供了一個異步的 `next()` 來執行被包裹的生成器，並返回下一個遍歷的值。

***
> Example
   * zho

栗子 🌰

***
> In normal PHP, we may have an line iterator on a file stream like this\:
   * zho

在常規的 PHP，我們可以寫出像這樣的代碼，來疊代檔案串流中的每一行：

***
> function lines\(string \$file\) \: Generator \{&#10;&#9;\$fh \= fopen\(\$file, \"rt\"\)\;&#10;&#9;try \{&#10;&#9;&#9;while\(\(\$line \= fgets\(\$fh\)\) !\=\= false\) \{&#10;&#9;&#9;&#9;yield \$line\;&#10;&#9;&#9;\}&#10;&#9;\} finally \{&#10;&#9;&#9;fclose\(\$fh\)\;&#10;&#9;\}&#10;\}&#10;&#10;function count\_empty\_lines\(string \$file\) \{&#10;&#9;\$count \= 0\;&#10;&#9;foreach\(lines\(\$file\) as \$line\) \{&#10;&#9;&#9;if\(trim\(\$line\) \=\=\= \"\"\) \$count\+\+\;&#10;&#9;\}&#10;&#9;return \$count\;&#10;\}&#10;
   * zho

function lines\(string \$file\) \: Generator \{&#10;&#9;\$fh \= fopen\(\$file, \"rt\"\)\;&#10;&#9;try \{&#10;&#9;&#9;while\(\(\$line \= fgets\(\$fh\)\) !\=\= false\) \{&#10;&#9;&#9;&#9;yield \$line\;&#10;&#9;&#9;\}&#10;&#9;\} finally \{&#10;&#9;&#9;fclose\(\$fh\)\;&#10;&#9;\}&#10;\}&#10;&#10;function count\_empty\_lines\(string \$file\) \{&#10;&#9;\$count \= 0\;&#10;&#9;foreach\(lines\(\$file\) as \$line\) \{&#10;&#9;&#9;if\(trim\(\$line\) \=\=\= \"\"\) \$count\+\+\;&#10;&#9;\}&#10;&#9;return \$count\;&#10;\}&#10;

***
> What if we have async versions of `fopen`, `fgets` and `fclose`
> and want to reimplement this `lines` function as async?
   * zho

但如果我們有異步的 `fopen` 、 `fgets` 和 `fclose` ，並需要重寫 `lines` 函數以實現異步呢？

***
> We would use the `Traverser` class instead\:
   * zho

我們將使用疊代器：

***
> function async\_lines\(string \$file\) \: Generator \{&#10;&#9;\$fh \= yield from async\_fopen\(\$file, \"rt\"\)\;&#10;&#9;try \{&#10;&#9;&#9;while\(true\) \{&#10;&#9;&#9;&#9;\$line \= yield from async\_fgets\(\$fh\)\;&#10;&#9;&#9;&#9;if\(\$line \=\=\= false\) \{&#10;&#9;&#9;&#9;&#9;return\;&#10;&#9;&#9;&#9;\}&#10;&#9;&#9;&#9;yield \$line \=\> Await\:\:VALUE\;&#10;&#9;&#9;\}&#10;&#9;\} finally \{&#10;&#9;&#9;yield from async\_fclose\(\$fh\)\;&#10;&#9;\}&#10;\}&#10;&#10;function async\_count\_empty\_lines\(string \$file\) \: Generator \{&#10;&#9;\$count \= 0\;&#10;&#10;&#9;\$traverser \= new Traverser\(async\_lines\(\$file\)\)\;&#10;&#9;while\(yield from \$traverser\-\>next\(\$line\)\) \{&#10;&#9;&#9;if\(trim\(\$line\) \=\=\= \"\"\) \$count\+\+\;&#10;&#9;\}&#10;&#10;&#9;return \$count\;&#10;\}&#10;
   * zho

function async\_lines\(string \$file\) \: Generator \{&#10;&#9;\$fh \= yield from async\_fopen\(\$file, \"rt\"\)\;&#10;&#9;try \{&#10;&#9;&#9;while\(true\) \{&#10;&#9;&#9;&#9;\$line \= yield from async\_fgets\(\$fh\)\;&#10;&#9;&#9;&#9;if\(\$line \=\=\= false\) \{&#10;&#9;&#9;&#9;&#9;return\;&#10;&#9;&#9;&#9;\}&#10;&#9;&#9;&#9;yield \$line \=\> Await\:\:VALUE\;&#10;&#9;&#9;\}&#10;&#9;\} finally \{&#10;&#9;&#9;yield from async\_fclose\(\$fh\)\;&#10;&#9;\}&#10;\}&#10;&#10;function async\_count\_empty\_lines\(string \$file\) \: Generator \{&#10;&#9;\$count \= 0\;&#10;&#10;&#9;\$traverser \= new Traverser\(async\_lines\(\$file\)\)\;&#10;&#9;while\(yield from \$traverser\-\>next\(\$line\)\) \{&#10;&#9;&#9;if\(trim\(\$line\) \=\=\= \"\"\) \$count\+\+\;&#10;&#9;\}&#10;&#10;&#9;return \$count\;&#10;\}&#10;

***
> Interrupting a generator
   * zho

中斷疊代器

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
   * zho

如果一個生成器的所有 `yield` 沒被完全消耗，在 `finally` 中進行 `yield` 可能會導致崩潰。
因此在 `finally` 中進行異步操作時，你 __必須__ 把遍歷器完全消耗。
若你希望中斷它。
你可以使用 `yield $traverser->interrupt()` ，不斷拋出參數中的異常（ 預設為 `SOFe\AwaitGenerator\InterruptException` ）至遍歷器包裹的疊代器，直到它停止執行。
請注意，如果底層的某個生成器捕捉到這個異常（因而吞噬了中斷的信號）， `interrupt` 就可能會拋出 `AwaitException`。

***
> It is not necessary to interrupt the traverser
> if there are no `finally` blocks containing `yield` statements\.
   * zho

如果 `finally` 中不含任何 `yield` ，則無須主動中斷。

***