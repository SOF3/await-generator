# await-generator
[![Build Status][ci-badge]][ci-page]
[![Codecov][codecov-badge]][codecov-page]

給予 PHP 「async/await 等待式異步」（代碼流控制）設計模式的程式庫。

## Documentation
Read the [await-generator tutorial][book] for an introduction
from generators and traditional async callbacks to await-generator.

## 使用 await-generator 的優勢
傳統的異步代碼流需要靠「callback 回調」（匿名 function）來實現。每個異步 function 都要開新的「回調」，然後把異步 function 後面的代碼整個搬進去，導致了代碼變成「callback hell 回調地獄」，難以被閱讀、管理。
<details>
    <summary>點擊以查看「回調地獄」例子</summary>
    
```php
load_data(function($data) {
    $init = count($data) === 0 ? init_data(...) : fn($then) => $then($data);
    $init(function($data) {
        $output = [];
        foreach($data as $k => $datum) {
            processData($datum, function($result) use(&$output, $data) {
                $output[$k] = $result;
                if(count($output) === count($data)) {
                    createQueries($output, function($queries) {
                        $run = function($i) use($queries, &$run) {
                            runQuery($queries[$i], function() use($i, $queries, $run) {
                                if($i === count($queries)) {
                                    $done = false;
                                    commitBatch(function() use(&$done) {
                                        if(!$done) {
                                            $done = true;
                                            echo "Done!\n";
                                        }
                                    });
                                    onUserClose(function() use(&$done) {
                                        if(!$done) {
                                            $done = true;
                                            echo "User closed!\n";
                                        }
                                    });
                                    onTimeout(function() use(&$done) {
                                        if(!$done) {
                                            $done = true;
                                            echo "Timeout!\n";
                                        }
                                    });
                                } else {
                                    $run($i + 1);
                                }
                            });
                        };
                    });
                }
            });
        }
    });
});
```
    
</details>
如果使用 await-generator ，以上代碼就可以被簡化為：

```php
$data = yield from load_data();
if(count($data) === 0) $data = yield from init_data();
$output = yield from Await::all(array_map(fn($datum) => processData($datum), $data));
$queries = yield from createQueries($output);
foreach($queries as $query) yield from runQuery($query);
[$which, ] = yield from Await::race([
    0 => commitBatch(),
    1 => onUserClose(),
    2 => onTimeout(),
])
echo match($which) {
    0 => "Done!\n",
    1 => "User closed!\n",
    2 => "Timeout!\n",
};
```

## 使用後的代碼可以維持「backward compatibility 回溯相容性」嗎？
是的，  await-generator 不會對已有的程式接口（API）造成任何限制。
你可以將所有涉及 await-generator 的代碼封閉在應用程式的內部。
但你確實應該直接把 generation function 當作程序接口。

await-generator 會在 `Await::f2c` method 開始進行異步代碼流控制，這樣「回調」 <!-- TODO: helpl wanted -->
await-generator starts an await context with the `Await::f2c` method,
with which you can adapt into the usual callback syntax:

```php
function oldApi($args, Closure $onSuccess) {
    Await::f2c(fn() => $onSuccess(yield from newApi($args)));
}
```

Or if you want to handle errors too:

```php
function newApi($args, Closure $onSuccess, Closure $onError) {
    Await::f2c(function() use($onSuccess, $onError) {
        try {
            $onSuccess(yield from newApi($args));
        } catch(Exception $ex) {
            $onError($ex);
        }
    });
}
```

傳統「回調」式的 function 也可以轉化成  `Await::promise` method 跟 JavaScript 的 `new Promise` 很像。它可以用來把 轉化成
You can continue to call functions implemented as callback style
using thein JS):

```php
yield from Await::promise(fn($resolve, $reject) => oldFunction($args, $resolve, $reject));
```

## 使用 await-generator 的*劣勢*
await-generator 有很多經常的坑人的地方：

- 忘了 `yield from Generator<void>` 的結果是代碼會毫無作用；
- 如果你的 function 沒有任何 `yield` 或者 `yield from` ， PHP 就不會把它當成 generator function 。（將所有 generator function 的 return 類型設成 `: Generator` 可預防意外行為）；
- `finally` blocks may never get executed if an async function never resolves
  (e.g. `Await::promise(fn($resolve) => null)`)；
- 如果 `try` 裡面的異步代碼沒有全面結束， `finally` 就不會被執行 （例： `Await::promise(fn($resolve) => null)`）；

儘管地方會導致一些問題， await-generator 的設計模式依然比「回調地獄」更難出 bug 。

## 不是有 fibers 嗎？
雖然這樣說很主觀，但本人相對地不喜歡 fibers ，
This might be a subjective comment,
but I do not prefer fibers for a few reasons:

### Explicit suspension in type signature
![fiber.jpg](./fiber.jpeg)

For example, it is easy to tell from the type signature that
`$channel->send($value): Generator<void>` suspends until the value is sent
and `$channel->sendBuffered($value): void`
is a non-suspending method that returns immediately.
Type signatures are often self-explanatory.

Of course, users could call `sleep()` anyway,
but it is quite obvious to everyone that `sleep()` blocks the whole runtime
(if they didn't already know, they will find out when the whole world stops).

### Concurrent states
When a function suspends, many other things can happen.
Indeed, calling a function allows the implementation to call any other functions
which could modify your states anyway,
but a sane, genuine implementation of e.g. an HTTP request
wouldn't call functions that modify the private states of your library.
But this assumption does not hold with fibers
because the fiber is preempted and other fibers can still modify the private states.
This means you have to check for possible changes in private properties
every time you call any function that *might* be suspending.

On the other hand, using explicit await,
it is obvious where exactly the suspension points are,
and you only need to check for state mutations at the known suspension points.

### Trapping suspension points
await-generator provides a feature called ["trapping"][trap-pr],
which allows users to add pre-suspend and pre-resume hooks to a generator.
This is simply achieved by adding an adapter to the generator,
and does not even require explicit support from the await-generator runtime.
This is currently not possible with fibers.

[book]: https://sof3.github.io/await-generator/master/
[ci-badge]: https://github.com/SOF3/await-generator/workflows/CI/badge.svg
[ci-page]: https://github.com/SOF3/await-generator/actions?query=workflow%3ACI
[codecov-badge]: https://img.shields.io/codecov/c/github/codecov/example-python.svg
[codecov-page]: https://codecov.io/gh/SOF3/await-generator
[trap-pr]: https://github.com/SOF3/await-generator/pull/106
