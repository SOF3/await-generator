> Exposing a generator to normal API
   * zho

生成器作為程式接口

***
> Recall that generator functions do not do anything when they get called\.
> Eventually, we have to call the generator function from a non\-await\-generator context\.
> We can use the `Await::g2c` function for this\:
   * zho

如果你還記得——生成器函數在調用時不會有任何動作。
因此我們最終在非 await-generator 的環境下調用它時，就可以使用 `Await::g2c` 。

***
> private function generateFunction\(\)\: Generator \{&#10;&#9;\/\/ some async logic&#10;\}&#10;&#10;Await\:\:g2c\(\$this\-\>generatorFunction\(\)\)\;&#10;
   * zho

private function generateFunction\(\)\: Generator \{&#10;&#9;\/\/ some async logic&#10;\}&#10;&#10;Await\:\:g2c\(\$this\-\>generatorFunction\(\)\)\;&#10;

***
> Sometimes we want to write the generator function as a closure
> and pass it directly\:
   * zho

「 `generateFunction()` 包含某些異步邏輯」。
有時候我們想把生成器函數寫成匿名函數並直接遞交：

***
> Await\:\:f2c\(function\(\)\: Generator \{&#10;&#9;\/\/ some async logic&#10;\}\)\;&#10;
   * zho

Await\:\:f2c\(function\(\)\: Generator \{&#10;&#9;\/\/ some async logic&#10;\}\)\;&#10;

***
> You can also use `Await::g2c`\/`Await::f2c`
> to schedule a separate async function in the background\.
   * zho

你更可以使用以上那兩個函數（ `Await::g2c`\/`Await::f2c` ）在背景排程更多分離的異步函數。

***