<?php

/*
 * await-generator
 *
 * Copyright (C) 2018 SOFe
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace SOFe\AwaitGenerator;

use Exception;
use Generator;
use function count;
use function is_int;

/**
 * await-generator is a wrapper to convert a traditional callback-async function into async/await functions.
 *
 * ### Why await-generator?
 * The callback-async function requires passing and creating many onComplete callables throughout the code, making the
 * code very unreadable, known as the "callback hell". The async/await approach allows code to be written linearly and
 * in normal language control structures (e.g. `if`, `for`, `return`), as if the code was not written async.
 *
 * ### Can I maintain backward compatibility?
 * As a wrapper, the whole `Await` can be used as a callback-async function, and the ultimate async functions can also
 * be callback-async functions, but the logic between can be purely written in async/await style. Therefore, the entry
 * API can still be callback-async style, and no changes are required in your library methods that accept callback-async
 * calling.
 *
 * ### How to migrate to async/await pattern easily?
 * The following steps are recommended:
 * - For any function with a `callable $onComplete` parameter you want to migrate, trace up its caller stack until there
 * are external API methods that you can't change, or until there are no more callers that pass an `$onComplete` to
 * trace.
 * - For this "ultimate caller" function, wrap all the code in an `Await::closure()` call such that
 *   - the first parameter is a generator function that wraps the original code
 *   - the second parameter is the input `$onComplete` (if any)
 * - Now migrate the code in the first parameter. There are three types of statements that you need to change:
 *   - If it is an internal async function (something that you just traced up),
 *     1. change it to `yield Await::FROM => async_function()`, and remove the callable parameters in the code
 *     2. modify the called function's signature so that it no longer requires a callable, and returns a Generator
 *     3. migrate the code inside the function in the same way (no need to wrap with `Await::closure()`)
 *   - If it is an external async function (something that you can't change),
 *     1. change it to `yield Await::ASYNC => async_function(yield)`, where the second `yield` should be placed at the
 * place the callable should be passed
 *   - For both internal and external async functions, the `yield` statement can be used to receive the values returned (or normally passed to `$onComplete`) from the function.
 *   - For any original `$onComplete()` + `return` calls, return the args originally for `$onComplete` directly in an
 * array. If there is only one element, it does not need to be wrapped with an array unless it is null or an array
 * itself.
 */
class Await{
	public const CALLBACK = "callback";
	public const FROM = "from";
	public const ASYNC = "async";

	/** @var Generator */
	protected $generator;
	/** @var callable|null */
	protected $onComplete;
	/** @var bool */
	protected $waiting = false;
	/** @var array */
	protected $waitingArgs;

	private function __construct(){
	}

	public static function closure(callable $closure, ?callable $onComplete = null) : Await{
		return self::func($closure(), $onComplete);
	}

	public static function func(Generator $generator, ?callable $onComplete = null) : Await{
		$await = new Await;
		$await->generator = $generator;
		$await->onComplete = $onComplete;
		$await->continue();

		return $await;
	}

	public function continue() : void{
		if(!$this->generator->valid()){
			if($this->onComplete !== null){
				$ret = $this->generator->getReturn();
				($this->onComplete)($ret);
			}
			return;
		}

		$key = $this->generator->key();
		$current = $this->generator->current();

		if(is_int($key)){
			$key = $current ?? Await::CALLBACK;
			$current = null;
		}

		switch($key){
			case Await::CALLBACK:
				$this->generator->send([$this, "waitComplete"]);
				$this->continue();
				return;

			case Await::ASYNC:
				$this->wait();
				return;

			case Await::FROM:
				if(!($current instanceof Generator)){
					throw $this->generator->throw(new Exception("Can only yield from a generator"));
				}
				self::func($current, [$this, "waitComplete"]);
				$this->wait();
				return;

			default:
				throw $this->generator->throw(new Exception("Unknown yield mode $key"));
		}
	}

	public function waitComplete(...$args) : void{
		$this->waitingArgs = $args;
		$this->wait();
	}

	private function wait() : void{
		if(!$this->waiting){
			$this->waiting = true;
			return;
		}

		$this->waiting = false;
		$this->generator->send(count($this->waitingArgs) === 1 ? $this->waitingArgs[0] : $this->waitingArgs);
		$this->continue();
	}
}
