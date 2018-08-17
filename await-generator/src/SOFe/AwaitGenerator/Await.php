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

use Generator;
use RuntimeException;
use Throwable;
use UnexpectedValueException;
use function assert;
use function count;
use function is_callable;

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
class Await extends AbstractPromise{
	public const RESOLVE = "resolve";
	public const REJECT = "reject";
	public const ONCE = "once";
	public const ALL = "all";

	/** @var Generator */
	protected $generator;
	/** @var callable|null */
	protected $onComplete;
	/** @var callable[]|null[] */
	protected $catches = [];
	/** @var bool */
	protected $sleeping;
	/** @var AbstractPromise[] */
	protected $promiseQueue = [];
	/** @var VoidCallbackPromise|null */
	protected $lastResolveUnrejected = null;

	protected function __construct(){
	}

	/**
	 * Converts a Function<AwaitGenerator> to a VoidCallback
	 *
	 * @param callable       $closure
	 * @param callable|null  $onComplete
	 * @param array|callable $catches
	 *
	 * @return Await
	 */
	public static function f2c(callable $closure, ?callable $onComplete = null, $catches = []) : Await{
		return self::g2c($closure(), $onComplete, $catches);
	}

	/**
	 * Converts an AwaitGenerator to a VoidCallback
	 *
	 * @param Generator      $generator
	 * @param callable|null  $onComplete
	 * @param array|callable $catches
	 *
	 * @return Await
	 */
	public static function g2c(Generator $generator, ?callable $onComplete = null, $catches = []) : Await{
		$await = new Await;
		$await->generator = $generator;
		$await->onComplete = $onComplete;
		$await->catches = is_callable($catches) ? ["" => $catches] : $catches;
		$await->wakeupFlat([$generator, "rewind"]);
		return $await;
	}

	/**
	 * A wrapper around wakeup() to convert deep recursion to tail recursion
	 *
	 * @param callable|null $executor
	 */
	public function wakeupFlat(?callable $executor) : void{
		while($executor !== null){
			$executor = $this->wakeup($executor);
		}
	}

	/**
	 * Calls $executor and returns the next function to execute
	 *
	 * @param callable $executor a function that triggers the execution of the generator
	 *
	 * @return callable|null
	 */
	public function wakeup(callable $executor) : ?callable{
		try{
			$this->sleeping = false;
			$executor();
		}catch(Throwable $throwable){
			$this->reject($throwable);
			return null;
		}

		if(!$this->generator->valid()){
			$ret = $this->generator->getReturn();
			$this->resolve($ret);
			return null;
		}

		$key = $this->generator->key();
		$current = $this->generator->current() ?? self::RESOLVE;

		if($current === self::RESOLVE){
			return function() : void{
				$promise = new VoidCallbackPromise($this);
				$this->promiseQueue[] = $promise;
				$this->lastResolveUnrejected = $promise;
				$this->generator->send([$promise, "resolve"]);
			};
		}

		if($current === self::REJECT){
			return function() : void{
				if($this->lastResolveUnrejected === null){
					throw new RuntimeException("Cannot yield Await::REJECT without yielding Await::RESOLVE first; they must be yielded in pairs");
				}
				$promise = $this->lastResolveUnrejected;
				$this->lastResolveUnrejected = null;
				$this->generator->send([$promise, "reject"]);
			};
		}

		$this->lastResolveUnrejected = null;

		if($current === self::ONCE || $current === self::ALL){
			if($current === self::ONCE && count($this->promiseQueue) !== 1){
				throw new RuntimeException("Yielded Await::ONCE when the pending queue size is " . count($this->promiseQueue) . " != 1");
			}

			$results = [];

			foreach($this->promiseQueue as $promise){
				if($promise->state === self::STATE_PENDING){
					$this->sleeping = true;
					return null;
				}
				if($promise->state === self::STATE_REJECTED){
					foreach($this->promiseQueue as $p){
						$p->cancelled = true;
					}
					$this->promiseQueue = [];
					$ex = $promise->rejected;
					return function() use($ex) : void{
						$this->generator->throw($ex);
					};
				}
				assert($promise->state === self::STATE_RESOLVED);
				$results[] = $promise->resolved;
			}
			// all resolved
			$this->promiseQueue = [];
			return function() use($current, $results) : void{
				$this->generator->send($current === self::ONCE ? $results[0] : $results);
			};
		}

		if($current instanceof Generator){
			// TODO implement
		}

		throw new UnexpectedValueException("Unknown yield value: $current");
	}

	public function recheckPromiseQueue() : void{
		assert($this->sleeping);
		$current = $this->generator->current();
		$results = [];
		foreach($this->promiseQueue as $promise){
			if($promise->state === self::STATE_PENDING){
				return;
			}
			if($promise->state === self::STATE_REJECTED){
				foreach($this->promiseQueue as $p){
					$p->cancelled = true;
				}
				$this->promiseQueue = [];
				$ex = $promise->rejected;
				$this->wakeupFlat(function() use($ex) : void{
					$this->generator->throw($ex);
				});
				return;
			}
			assert($promise->state === self::STATE_RESOLVED);
			$results[] = $promise->resolved;
		}
		// all resolved
		$this->promiseQueue = [];
		$this->wakeupFlat(function() use($current, $results){
			$this->generator->send($current === self::ONCE ? $results[0] : $results);
		});
	}

	public function resolve($value) : void{
		if(!empty($this->promiseQueue)){
			$this->reject(new UnresolvedCallbackException());
			return;
		}
		$this->sleeping = true;
		parent::resolve($value);
		if($this->onComplete){
			($this->onComplete)($this->resolved);
		}
	}

	public function reject(Throwable $throwable) : void{
		$this->sleeping = true;
		parent::reject($throwable);
		foreach($this->catches as $class => $onError){
			if($class === "" || $throwable instanceof $class){
				$onError($throwable);
				return;
			}
		}
		throw new RuntimeException("Unhandled async exception", 0, $throwable);
	}

	public function isSleeping() : bool{
		return $this->sleeping;
	}
}
