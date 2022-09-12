[Eng](../README.md) | [繁](../zho) | 简
# await-generator
[![Build Status][ci-badge]][ci-page]
[![Codecov][codecov-badge]][codecov-page]

给予 PHP 「async/await 等待式异步」（代码流控制）设计模式的程序库。

## 文档
建议先阅读 [await-generator 教学（中文版赶工中）](../book)，它涵盖了生成器、传统「回调式异步」，再到 await-generator 等概念的介绍。

以下部分名词在 await-generator 教学中都更详细地讲解（「回调」等）。

## await-generator 的优势
传统的异步代码流需要靠回调（匿名函数）来实现。
每个异步函数都要开新的回调，然后把异步函数后面的代码整个搬进去，导致了代码变成「callback hell 回调地狱」，难以被阅读、管理。
<details>
    <summary>点击以查看「回调地狱」例子</summary>
    
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
如果使用 await-generator ，以上代码就可以被简化为：

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

## 使用后的代码可以维持回溯相容性吗？
是的， await-generator 不会对已有的接口造成任何限制。
你可以将所有涉及 await-generator 的代码封闭在程序的内部。
但你确实应该把生成器函数直接当作程序接口。

await-generator 会在 `Await::f2c` 开始进行异步代码流控制，你可以将它视为「等待式」至「回调式」的转接头。

```php
function oldApi($args, Closure $onSuccess) {
    Await::f2c(fn() => $onSuccess(yield from newApi($args)));
}
```

你也用它来处理错误：

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

「回调式」同样可以被 `Await::promise` method 转化成「等待式」。
它跟 JavaScript 的 `new Promise` 很像：

```php
yield from Await::promise(fn($resolve, $reject) => oldFunction($args, $resolve, $reject));
```

## await-generator 的*劣势*
await-generator 也有很多经常坑人的地方：

- 忘了 `yield from` 的代码会毫无作用；
- 如果你的函数没有任何 `yield` 或者 `yield from` ， PHP 就不会把它当成生成器函数（在所有应为生成器的函数类型注释中加上 `: Generator` 可减轻影响）；
- 如果异步代码没有全面结束， `finally` 里面的代码也不会被执行（例： `Await::promise(fn($resolve) => null)`）；

尽管一些地方会导致问题， await-generator 的设计模式出 bug 的机会依然比「回调地狱」少 。

## 不是有纤程吗？
虽然这样说很主观，但本人因为以下纤程缺少的特色而相对地不喜欢它：

### 靠类型注释就能区分异步、非异步函数
> 先生，你已在暂停的纤程待了三十秒。<br />
> 因为有人实现一个界面时调用了 `Fiber::suspend() ` 。

![../../fiber.jpg](https://github.com/SOF3/await-generator/raw/master/fiber.jpeg)

> 好家伙，我都等不及要回应我的 HTTP 请求了。<br />
> 框架肯定还没把它给超时清除。

例如能直观地看出 `$channel->send($value): Generator<void>` 会暂停代码流至有数值被送入生成器； `$channel->sendBuffered($value): void`
则不会暂停代码流，这个 method 的代码会在一次过执行后回传。
类型注释通常是不言自明的。

当然，用户可以直接调用 `sleep()` ，但大家都应清楚 `sleep()` 会卡住整个线程（就算他们不懂也会在整个「世界」停止时发现）。

### 并发状态
当一个函数被暂停时会发生许多其他的事情。
调用函数时固然给予了实现者调用可修改状态函数的可能性，
但是一个正常的、合理的实现，例如 HTTP 请求所调用的函数不应修改你程序库的内部状态。
但是这个假设对于纤程来说并不成立，
因为当一个纤程被暂停后，其他纤程仍然可以修改你的内部状态。
每次你调用任何*可能*会被暂停的函数时，你都必须检查内部状态的可能变化。

await-generator 相比起纤程，异步、非异步代码能简单区分，且暂停点的确切位置显而易见。
因此你只需要在已知的暂停点检查状态的变化。

### 捕捉暂停点
await-generator 提供了一个叫做「[捕捉][trap-pr]」的功能。
它允许用户拦截生成器的暂停点和恢复点，在它暂停或恢复前执行一段加的插代码。
这只需透过向生成器添加一个转接头来实现。甚至不需要 await-generator 引擎的额外支援。
这目前在纤程中无法做到。

[book]: https://sof3.github.io/await-generator/master/
[ci-badge]: https://github.com/SOF3/await-generator/workflows/CI/badge.svg
[ci-page]: https://github.com/SOF3/await-generator/actions?query=workflow%3ACI
[codecov-badge]: https://img.shields.io/codecov/c/github/codecov/example-python.svg
[codecov-page]: https://codecov.io/gh/SOF3/await-generator
[trap-pr]: https://github.com/SOF3/await-generator/pull/106
