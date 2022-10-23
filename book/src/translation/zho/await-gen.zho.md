> Awaiting generators
   * zho

等待生成器

***
> Since every async function is implemented as a generator function,
> simply calling it will not have any effects\.
> Instead, you have to `yield from` the generator\.
   * zho

由於所有異步函數都是生成器函數，直接調用會毫無作用。
你需要在調用它後， `yield from` 它的生成器。

***
> function a\(\)\: Generator \{&#10;&#9;\/\/ some other async logic here&#10;&#9;return 1\;&#10;\}&#10;&#10;function main\(\)\: Generator \{&#10;&#9;\$a \= yield from \$this\-\>a\(\)\;&#10;&#9;var_dump\(\$a\)\;&#10;\}&#10;
   * zho

function a\(\)\: Generator \{&#10;&#9;\/\/ 某些異步行動&#10;&#9;return 1\;&#10;\}&#10;&#10;function main\(\)\: Generator \{&#10;&#9;\$a \= yield from \$this\-\>a\(\)\;&#10;&#9;var_dump\(\$a\)\;&#10;\}&#10;

***
> It is easy to forget to `yield from` the generator.
   * zho


「 `a()` 包含某些其它的異步邏輯」。
留意 `yield from` ，它很容易被忘加在生成器前。

***
> Handling errors
   * zho

處理錯誤

***
> `yield from` will throw an exception
> if the generator function you called threw an exception\.
   * zho

當你調用的生成器函數有拋出異常，你的函數也會在 `yield from` 它時拋出那個異常。
「 `err()` 包含某些其它的異步邏輯」。

***
> function err\(\)\: Generator \{&#10;&#9;\/\/ some other async logic here&#10;&#9;throw new Exception\(\"Test\"\)\;&#10;\}&#10;&#10;function main\(\)\: Generator \{&#10;&#9;try \{&#10;&#9;&#9;yield from err\(\)\;&#10;&#9;\} catch\(Exception \$e\) \{&#10;&#9;&#9;var_dump\(\$e\-\>getMessage\(\)\)\; \/\/ string\(4\) \"Test\"&#10;&#9;\}&#10;\}&#10;
   * zho

function err\(\)\: Generator \{&#10;&#9;\/\/ 某些異步行動&#10;&#9;throw new Exception\(\"測試\"\)\;&#10;\}&#10;&#10;function main\(\)\: Generator \{&#10;&#9;try \{&#10;&#9;&#9;yield from err\(\)\;&#10;&#9;\} catch\(Exception \$e\) \{&#10;&#9;&#9;var_dump\(\$e\-\>getMessage\(\)\)\; \/\/ string\(4\) \"測試\"&#10;&#9;\}&#10;\}&#10;

***
