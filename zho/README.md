[Eng](../README.md) | 繁 | [简](../chs)
# await-generator
[![Build Status][ci-badge]][ci-page]
[![Codecov][codecov-badge]][codecov-page]

給予 PHP 「async/await 等待式異步」（代碼流控制）設計模式的程式庫。

## 文檔
建議先閱讀 [await-generator 教學（中文版趕工中）](../book)，它涵蓋了生成器、傳統「回調式非同步」，再到 await-generator 等概念的介紹。

以下部分名詞在 await-generator 教學中都更詳細地講解（「回調」等）。

## await-generator 的優勢
傳統的異步代碼流需要靠回調（匿名函數）來實現。
每個異步函數都要開新的回調，然後把異步函數後面的代碼整個搬進去，導致了代碼變成「callback hell 回調地獄」，難以被閱讀、管理。
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

## 使用後的代碼可以維持回溯相容性嗎？
是的， await-generator 不會對已有的接口造成任何限制。
你可以將所有涉及 await-generator 的代碼封閉在程式的內部。
但你確實應該把生成器函數直接當作程式接口。

await-generator 會在 `Await::f2c` 開始進行異步代碼流控制，你可以將它視為「等待式」至「回調式」的轉接頭。

```php
function oldApi($args, Closure $onSuccess) {
    Await::f2c(fn() => $onSuccess(yield from newApi($args)));
}
```

你也用它來處理錯誤：

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

「回調式」同樣可以被 `Await::promise` method 轉化成「等待式」。
它跟 JavaScript 的 `new Promise` 很像：

```php
yield from Await::promise(fn($resolve, $reject) => oldFunction($args, $resolve, $reject));
```

## await-generator 的*劣勢*
await-generator 也有很多經常坑人的地方：

- 忘了 `yield from` 的代碼會毫無作用；
- 如果你的函數沒有任何 `yield` 或者 `yield from` ， PHP 就不會把它當成生成器函數（在所有應為生成器的函數類型註釋中加上 `: Generator` 可減輕影響）；
- 如果異步代碼沒有全面結束， `finally` 裏面的代碼也不會被執行（例： `Await::promise(fn($resolve) => null)`）；

儘管一些地方會導致問題， await-generator 的設計模式出 bug 的機會依然比「回調地獄」少 。

## 不是有纖程嗎？
雖然這樣說很主觀，但本人因為以下纖程缺少的特色而相對地不喜歡它：

### 靠類型註釋就能區分異步、非異步函數
> 先生，你已在暫停的纖程待了三十秒。<br />
> 因為有人實現一個界面時調用了 `Fiber::suspend() ` 。

![../../fiber.jpg](https://github.com/SOF3/await-generator/raw/master/fiber.jpeg)

> 好傢伙，我都等不及要回應我的 HTTP 請求了。<br />
> 框架肯定還沒把它給超時清除。

例如能直觀地看出 `$channel->send($value): Generator<void>` 會暫停代碼流至有數值被送入生成器； `$channel->sendBuffered($value): void`
則不會暫停代碼流，這個 method 的代碼會在一次過執行後回傳。
類型註釋通常是不言自明的。

當然，用戶可以直接調用 `sleep()` ，但大家都應清楚 `sleep()` 會卡住整個線程（就算他們不懂也會在整個「世界」停止時發現）。

### 並發狀態
當一個函數被暫停時會發生許多其他的事情。
調用函數時固然給予了實現者調用可修改狀態函數的可能性，
但是一個正常的、合理的實現，例如 HTTP 請求所調用的函數不應修改你程式庫的內部狀態。
但是這個假設對於纖程來說並不成立，
因為當一個纖程被暫停後，其他纖程仍然可以修改你的內部狀態。
每次你調用任何*可能*會被暫停的函數時，你都必須檢查內部狀態的可能變化。

await-generator 相比起纖程，異步、非異步代碼能簡單區分，且暫停點的確切位置顯而易見。
因此你只需要在已知的暫停點檢查狀態的變化。

### 捕捉暫停點
await-generator 提供了一個叫做「[捕捉][trap-pr]」的功能。
它允許用戶攔截生成器的暫停點和恢復點，在它暫停或恢復前執行一段加的插代碼。
這只需透過向生成器添加一個轉接頭來實現。甚至不需要 await-generator 引擎的額外支援。
這目前在纖程中無法做到。

[book]: https://sof3.github.io/await-generator/master/
[ci-badge]: https://github.com/SOF3/await-generator/workflows/CI/badge.svg
[ci-page]: https://github.com/SOF3/await-generator/actions?query=workflow%3ACI
[codecov-badge]: https://img.shields.io/codecov/c/github/codecov/example-python.svg
[codecov-page]: https://codecov.io/gh/SOF3/await-generator
[trap-pr]: https://github.com/SOF3/await-generator/pull/106
