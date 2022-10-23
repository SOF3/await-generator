> Awaiting generators
   * chs

等待生成器

***
> Since every async function is implemented as a generator function,
> simply calling it will not have any effects\.
> Instead, you have to `yield from` the generator\.
   * chs

由于所有异步函数都是生成器函数，直接调用会毫无作用。
你需要在调用它后， `yield from` 它的生成器。

***
> function a\(\)\: Generator \{&#10;&#9;\/\/ some other async logic here&#10;&#9;return 1\;&#10;\}&#10;&#10;function main\(\)\: Generator \{&#10;&#9;\$a \= yield from \$this\-\>a\(\)\;&#10;&#9;var_dump\(\$a\)\;&#10;\}&#10;
   * chs

function a\(\)\: Generator \{&#10;&#9;\/\/ 某些异步行动&#10;&#9;return 1\;&#10;\}&#10;&#10;function main\(\)\: Generator \{&#10;&#9;\$a \= yield from \$this\-\>a\(\)\;&#10;&#9;var_dump\(\$a\)\;&#10;\}&#10;

***
> It is easy to forget to `yield from` the generator.
   * chs


留意 `yield from` ，它很容易被忘加在生成器前。

***
> Handling errors
   * chs

处理错误

***
> `yield from` will throw an exception
> if the generator function you called threw an exception\.
   * chs

当你调用的生成器函数有抛出异常，你的函数也会在 `yield from` 它时抛出那个异常。

***
> function err\(\)\: Generator \{&#10;&#9;\/\/ some other async logic here&#10;&#9;throw new Exception\(\"Test\"\)\;&#10;\}&#10;&#10;function main\(\)\: Generator \{&#10;&#9;try \{&#10;&#9;&#9;yield from err\(\)\;&#10;&#9;\} catch\(Exception \$e\) \{&#10;&#9;&#9;var_dump\(\$e\-\>getMessage\(\)\)\; \/\/ string\(4\) \"Test\"&#10;&#9;\}&#10;\}&#10;
   * chs

function err\(\)\: Generator \{&#10;&#9;\/\/ 某些异步行动&#10;&#9;throw new Exception\(\"测试\"\)\;&#10;\}&#10;&#10;function main\(\)\: Generator \{&#10;&#9;try \{&#10;&#9;&#9;yield from err\(\)\;&#10;&#9;\} catch\(Exception \$e\) \{&#10;&#9;&#9;var_dump\(\$e\-\>getMessage\(\)\)\; \/\/ string\(4\) \"测试\"&#10;&#9;\}&#10;\}&#10;

***
