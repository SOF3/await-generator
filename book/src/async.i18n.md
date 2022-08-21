> Asynchronous programming
   * zho

異步代碼流

***
> Traditionally, when you call a function,
> it performs the required actions and returns after they\'re done\.
> In asynchronous programming,
> the program logic may be executed _after_ a function returns\.
   * zho

原本當我們調用一個函數，它會在完成所需的動作後終止或回傳數值。
但在異步代碼流中，這些動作大多數會在函數終止或回傳後才執行。

***
> This leads to two problems\.
> First, the function can\'t return you with any useful results,
> because the results are only available after the logic completes\.
> Second, you may do something else assuming the logic is completed,
> which leads to a bug\.
> For example\:
   * zho

這樣就會導致兩個問題。
一、該函數無法回傳異步動作過後才會出現的有用結果；二、如果你的代碼假設異步代碼已經完成，會導致潛在的 bug ，比如說：

***
> private \$data\;&#10;&#10;function loadData\(\$player\) \{&#10;&#9;\/\/ we will set \$this\-\>data\[\$player\] some time later\.&#10;\}&#10;&#10;function main\(\) \{&#10;&#9;\$this\-\>loadData\(\"SOFe\"\)\;&#10;&#9;echo \$this\-\>data\[\"SOFe\"\]\; \/\/ Undefined offset \"SOFe\"&#10;\}&#10;
   * zho

private \$data\;&#10;&#10;function loadData\(\$player\) \{&#10;&#9;\/\/ 假設 \$this\-\>data\[\$player\] 在一段時間後才被賦值。&#10;\}&#10;&#10;function main\(\) \{&#10;&#9;\$this\-\>loadData\(\"SOFe\"\)\;&#10;&#9;echo \$this\-\>data\[\"SOFe\"\]\; \/\/ Undefined offset \"SOFe\" （未賦值錯誤）&#10;\}&#10;

***
> Here, `loadData` is the function that loads data asynchronously\.
> `main` is implemented incorrectly, assuming that `loadData` is synchronous,
> i\.e\. it assumes that `$this->data["SOFe"]` is initialized\.
   * zho

`loadData` 這個函數會異步地載入資料。
`main` 沒有正確地實現，因為它假設 `loadData` 是同步的。
（它假設 `$this->data["SOFe"]` 在 `loadData` 結束前就已被賦值。）

***
> Using callbacks
   * zho

使用回調

***
> One of the simplest ways to solve this problem is to use callbacks\.
> The caller can pass a closure to the async function,
> then the async function will run this closure when it has finished\.
> An example function signature would be like this\:
   * zho

這個問題最簡單直接的解決方法就是使用「callback 回調」（匿名函數）。
異步函數的調用者可以傳遞一個回調給它，它就可以在完成異步動作後調用該回調。
請看例子中函數的參數：

***
> function loadData\(\$player, Closure \$callback\) \{&#10;&#9;\/\/ \$callback will be called when player data have been loaded\.&#10;\}&#10;&#10;function main\(\) \{&#10;&#9;\$this\-\>loadData\(\"SOFe\", function\(\) \{&#10;&#9;&#9;echo \$this\-\>data\[\"SOFe\"\]\; \/\/ this is guaranteed to work now&#10;&#9;\}\)\;&#10;\}&#10;
   * zho

function loadData\(\$player, Closure \$callback\) \{&#10;&#9;\/\/ \$callback 會在玩家（$player）資料完成載入後調用。&#10;\}&#10;&#10;function main\(\) \{&#10;&#9;\$this\-\>loadData\(\"SOFe\", function\(\) \{&#10;&#9;&#9;echo \$this\-\>data\[\"SOFe\"\]\; \/\/ 現在就能確保它已被賦值&#10;&#9;\}\)\;&#10;\}&#10;

***
> The `$callback` will be called when some other logic happens\.
> This depends on the implementation of the `loadData` logic\.
> This may be when a player sends a certain packet,
> or when a scheduled task gets run,
> or other scenarios\.
   * zho

回調也可以用於其他情況，這視乎 `loadData` 是怎麼實現的。
可以是玩家發送特定的封包時、當一個排程任務被執行時等場合。

***
> More complex callbacks
   * zho

更複雜的回調

***
> \(This section is deliberately complicated and hard to understand,
> because the purpose is to tell you that using callbacks is bad\.\)
   * zho

（這個部分是故意寫到很複雜且難以理解的，目的就是要告訴你回調到底有多糟糕。）

***
> What if we want to call multiple async functions one by one?
> In synchronous code, it would be simple\:
   * zho

那如果我們想依次（分次序地）調用函數呢？
這在同步的代碼流就很簡單：

***
> \$a \= a\(\)\;&#10;\$b \= b\(\$a\)\;&#10;\$c \= c\(\$b\)\;&#10;\$d \= d\(\$c\)\;&#10;var_dump\(\$d\)\;&#10;
   * zho

\$a \= a\(\)\;&#10;\$b \= b\(\$a\)\;&#10;\$c \= c\(\$b\)\;&#10;\$d \= d\(\$c\)\;&#10;var_dump\(\$d\)\;&#10;

***
> In async code, we might need to do this \(let\'s say `a`, `b`, `c`, `d` are async\)\:
   * zho

但是在異步的代碼流，我們則需要這樣（以下 `a`、`b`、`c`、`d` 為異步函數）：

***
> a\(function\(\$a\) \{&#10;&#9;b\(\$a, function\(\$b\) \{&#10;&#9;&#9;c\(\$b, function\(\$c\) \{&#10;&#9;&#9;&#9;d\(\$c, function\(\$d\) \{&#10;&#9;&#9;&#9;&#9;var_dump\(\$d\)\;&#10;&#9;&#9;&#9;\}\)\;&#10;&#9;&#9;\}\)\;&#10;&#9;\}\)\;&#10;\}\)\;&#10;
   * zho

a\(function\(\$a\) \{&#10;&#9;b\(\$a, function\(\$b\) \{&#10;&#9;&#9;c\(\$b, function\(\$c\) \{&#10;&#9;&#9;&#9;d\(\$c, function\(\$d\) \{&#10;&#9;&#9;&#9;&#9;var_dump\(\$d\)\;&#10;&#9;&#9;&#9;\}\)\;&#10;&#9;&#9;\}\)\;&#10;&#9;\}\)\;&#10;\}\)\;&#10;

***
> Looks ugly, but readable enough\.
> It might look more confusing if we need to pass `$a` to `$d` though\.
   * zho

儘管很醜，代碼仍然能夠閱讀。
但如果我們需要把 `$a` 帶進 `$d` 就會更加混亂。

***
> But what if we want to do if\/else?
> In synchronous code, it looks like this\:
   * zho

而如果我們想用 if、else 呢？
同步代碼流會是這樣：

***
> \$a \= a\(\)\;&#10;if\(\$a !\=\= null\) \{&#10;&#9;\$output \= b\(\$a\)\;&#10;\} else \{&#10;&#9;\$output \= c\(\) \+ 1\;&#10;\}&#10;&#10;\$d \= very\_complex\_code\(\$output\)\;&#10;\$e \= that\_deals\_with\(\$output\)\;&#10;var\_dump\(\$d \+ \$e \+ \$a\)\;&#10;
   * zho

\$a \= a\(\)\;&#10;if\(\$a !\=\= null\) \{&#10;&#9;\$output \= b\(\$a\)\;&#10;\} else \{&#10;&#9;\$output \= c\(\) \+ 1\;&#10;\}&#10;&#10;\$d \= very\_complex\_code\(\$output\)\;&#10;\$e \= that\_deals\_with\(\$output\)\;&#10;var\_dump\(\$d \+ \$e \+ \$a\)\;&#10;

***
> In async code, it is much more confusing\:
   * zho

到了異步代碼流就顯得更加混亂了：

***
> a\(function\(\$a\) \{&#10;&#9;if\(\$a !\=\= null\) \{&#10;&#9;&#9;b\(\$a, function\(\$output\) use\(\$a\) \{&#10;&#9;&#9;&#9;&#9;\$d \= very\_complex\_code\(\$output\)\;&#10;&#9;&#9;&#9;&#9;\$e \= that\_deals\_with\(\$output\)\;&#10;&#9;&#9;&#9;&#9;var\_dump\(\$d \+ \$e \+ \$a\)\;&#10;&#9;&#9;\}\)\;&#10;&#9;\} else \{&#10;&#9;&#9;c\(function\(\$output\) use\(\$a\) \{&#10;&#9;&#9;&#9;&#9;\$output \= \$output \+ 1\;&#10;&#9;&#9;&#9;&#9;\$d \= very\_complex\_code\(\$output\)\;&#10;&#9;&#9;&#9;&#9;\$e \= that\_deals\_with\(\$output\)\;&#10;&#9;&#9;&#9;&#9;var\_dump\(\$d \+ \$e \+ \$a\)\;&#10;&#9;&#9;\}\)\;&#10;&#9;\}&#10;\}\)\;&#10;
   * zho

a\(function\(\$a\) \{&#10;&#9;if\(\$a !\=\= null\) \{&#10;&#9;&#9;b\(\$a, function\(\$output\) use\(\$a\) \{&#10;&#9;&#9;&#9;&#9;\$d \= very\_complex\_code\(\$output\)\;&#10;&#9;&#9;&#9;&#9;\$e \= that\_deals\_with\(\$output\)\;&#10;&#9;&#9;&#9;&#9;var\_dump\(\$d \+ \$e \+ \$a\)\;&#10;&#9;&#9;\}\)\;&#10;&#9;\} else \{&#10;&#9;&#9;c\(function\(\$output\) use\(\$a\) \{&#10;&#9;&#9;&#9;&#9;\$output \= \$output \+ 1\;&#10;&#9;&#9;&#9;&#9;\$d \= very\_complex\_code\(\$output\)\;&#10;&#9;&#9;&#9;&#9;\$e \= that\_deals\_with\(\$output\)\;&#10;&#9;&#9;&#9;&#9;var\_dump\(\$d \+ \$e \+ \$a\)\;&#10;&#9;&#9;\}\)\;&#10;&#9;\}&#10;\}\)\;&#10;

***
> But we don\'t want to copy\-paste the three lines of duplicated code\.
> Maybe we can assign the whole closure to a variable\:
   * zho

如果我們不想複製黏貼那三行重複的代碼，就需要將回調預先儲存在一個變數中：

***
> a\(function\(\$a\) \{&#10;&#9;\$closure \= function\(\$output\) use\(\$a\) \{&#10;&#9;&#9;\$d \= very\_complex\_code\(\$output\)\;&#10;&#9;&#9;\$e \= that\_deals\_with\(\$output\)\;&#10;&#9;&#9;var\_dump\(\$d \+ \$e \+ \$a\)\;&#10;&#9;\}\;&#10;&#10;&#9;if\(\$a !\=\= null\) \{&#10;&#9;&#9;b\(\$a, \$closure\)\;&#10;&#9;\} else \{&#10;&#9;&#9;c\(function\(\$output\) use\(\$closure\) \{&#10;&#9;&#9;&#9;\$closure\(\$output \+ 1\)\;&#10;&#9;&#9;\}\)\;&#10;&#9;\}&#10;\}\)\;&#10;
   * zho

a\(function\(\$a\) \{&#10;&#9;\$closure \= function\(\$output\) use\(\$a\) \{&#10;&#9;&#9;\$d \= very\_complex\_code\(\$output\)\;&#10;&#9;&#9;\$e \= that\_deals\_with\(\$output\)\;&#10;&#9;&#9;var\_dump\(\$d \+ \$e \+ \$a\)\;&#10;&#9;\}\;&#10;&#10;&#9;if\(\$a !\=\= null\) \{&#10;&#9;&#9;b\(\$a, \$closure\)\;&#10;&#9;\} else \{&#10;&#9;&#9;c\(function\(\$output\) use\(\$closure\) \{&#10;&#9;&#9;&#9;\$closure\(\$output \+ 1\)\;&#10;&#9;&#9;\}\)\;&#10;&#9;\}&#10;\}\)\;&#10;

***
> Oh no, this is getting out of control\.
> Think about how complicated this would become when
> we want to use asynchronous functions in loops!
   * zho

不！這代碼已經逐漸失去掌控了。
想想看，如果我們想在迴圈中使用異步函數，會變得多麼複雜。
我們想在循環中使用非同步函數時，這將變得多麼複雜啊

***
> The await\-generator library allows users to write async code in synchronous style\.
> As you might have guessed, the `yield` keyword is a replacement for callbacks\.
   * zho

await\-generator 程式庫讓使用者能夠將異步代碼以同步代碼的風格表達。
也許你已經猜到， `yield` 這個語句將用來取代回調。

***
