> Exposing a generator to normal API
   * chs

生成器作为程序接口

***
> Recall that generator functions do not do anything when they get called\.
> Eventually, we have to call the generator function from a non\-await\-generator context\.
> We can use the `Await::g2c` function for this\:
   * chs

如果你还记得——生成器函数在调用时不会有任何动作。
因此我们最终在非 await-generator 的环境下调用它时，就可以使用 `Await::g2c` 。

***
> private function generateFunction\(\)\: Generator \{&#10;&#9;\/\/ some async logic&#10;\}&#10;&#10;Await\:\:g2c\(\$this\-\>generatorFunction\(\)\)\;&#10;
   * chs

private function generateFunction\(\)\: Generator \{&#10;&#9;\/\/ some async logic&#10;\}&#10;&#10;Await\:\:g2c\(\$this\-\>generatorFunction\(\)\)\;&#10;

***
> Sometimes we want to write the generator function as a closure
> and pass it directly\:
   * chs

「 `generateFunction()` 包含某些异步逻辑」。
有时候我们想把生成器函数写成匿名函数并直接递交：

***
> Await\:\:f2c\(function\(\)\: Generator \{&#10;&#9;\/\/ some async logic&#10;\}\)\;&#10;
   * chs

Await\:\:f2c\(function\(\)\: Generator \{&#10;&#9;\/\/ some async logic&#10;\}\)\;&#10;

***
> You can also use `Await::g2c`\/`Await::f2c`
> to schedule a separate async function in the background\.
   * chs

你更可以使用以上那两个函数（ `Await::g2c`\/`Await::f2c` ）在背景排程更多分离的异步函数。

***