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

use Error;
use Exception;
use Generator;
use ReflectionClass;
use ReflectionGenerator;
use Throwable;
use const DEBUG_BACKTRACE_PROVIDE_OBJECT;
use function array_merge;
use function assert;
use function count;
use function is_a;
use function is_callable;

class Await extends PromiseState{
	public const RESOLVE = "resolve";
	public const RESOLVE_MULTI = [Await::RESOLVE];
	public const REJECT = "reject";
	public const ONCE = "once";
	public const ALL = "all";
	public const RACE = "race";

	/** @var bool */
	public static $debug = true;

	/** @var bool */
	private $ultimate;
	/** @var Generator */
	protected $generator;
	/** @var callable|null */
	protected $onComplete;
	/**
	 * @var callable[]
	 * @phpstan-var array<string, callable>
	 */
	protected $catches = [];
	/** @var bool */
	protected $sleeping;
	/** @var PromiseState[] */
	protected $promiseQueue = [];
	/** @var AwaitChild|null */
	protected $lastResolveUnrejected = null;
	/** @var string|null */
	protected $current = null;

	/** @var array */
	protected $lastTrace = [];

	protected function __construct(bool $ultimate){
		$this->ultimate = $ultimate;
	}

	/**
	 * Converts a `Function<AwaitGenerator>` to a VoidCallback
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
		$await = new Await(true);
		$await->generator = $generator;
		$await->onComplete = $onComplete;
		$await->catches = is_callable($catches) ? ["" => $catches] : $catches;
		$executor = [$generator, "rewind"];
		while($executor !== null){
			$executor = $await->wakeup($executor);
		}
		return $await;
	}

	/**
	 * Given an array of generators,
	 * executes them simultaneously,
	 * and returns an array with each generator mapped to the value.
	 * Throws exception as soon as any of the generators throws an exception.
	 *
	 * @param Generator[] $generators
	 * @return Generator
	 */
	public static function all(array $generators) : Generator{
		if(count($generators) === 0){
			throw new AwaitException("Cannot await all on an empty array of generators");
		}

		foreach($generators as $k => $generator){
			$resolve = yield;
			$reject = yield self::REJECT;
			self::g2c($generator, function($result) use($k, $resolve) {
				$resolve([$k, $result]);
			}, $reject);
		}
		$all = yield self::ALL;
		$return = [];
		foreach($all as [$k, $result]) {
			$return[$k] = $result;
		}
		return $return;
	}

	/**
	 * Given an array of generators,
	 * executes them simultaneously,
	 * and returns a single-element array `[$k, $v]` as soon as any of the generators returns,
	 * with `$k` being the key of that generator in the array
	 * and `$v` being the value returned by the generator.
	 * Throws exception as soon as any of the generators throws an exception.
	 *
	 * Note that the not-yet-resolved generators will keep on running,
	 * but their return values or exceptions thrown will be ignored.
	 *
	 * The return value uses `[$k, $v]` instead of `[$k => $v]`.
	 * The user may use the format `[$k, $v] = yield Await::race(...);`
	 * to obtain `$k` and `$v` conveniently.
	 *
	 * @param Generator[] $generators
	 * @return Generator
	 */
	public static function race(array $generators) : Generator{
		if(count($generators) === 0){
			throw new AwaitException("Cannot race an empty array of generators");
		}

		foreach($generators as $k => $generator){
			$resolve = yield;
			$reject = yield self::REJECT;
			self::g2c($generator, function($result) use($k, $resolve) {
				$resolve([$k, $result]);
			}, $reject);
		}
		[$k, $result] = yield self::RACE;
		return [$k, $result];
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
	protected function wakeup(callable $executor) : ?callable{
		if(self::$debug){
			$ref = new ReflectionGenerator($this->generator);
			$this->lastTrace = $ref->getTrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);
			$this->lastTrace[] = [
				"file" => $ref->getExecutingFile(),
				"line" => $ref->getExecutingLine(),
				"function" => $ref->getFunction()->getName(),
				"args" => [],
			];
		}
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

		// $key = $this->generator->key();
		$this->current = $current = $this->generator->current() ?? self::RESOLVE;

		if($current === self::RESOLVE){
			return function() : void{
				$promise = new AwaitChild($this);
				$this->promiseQueue[] = $promise;
				$this->lastResolveUnrejected = $promise;
				$this->generator->send([$promise, "resolve"]);
			};
		}

		if($current === self::RESOLVE_MULTI){
			return function() : void{
				$promise = new AwaitChild($this);
				$this->promiseQueue[] = $promise;
				$this->lastResolveUnrejected = $promise;
				$this->generator->send(static function(...$args) use($promise) : void{
					$promise->resolve($args);
				});
			};
		}

		if($current === self::REJECT){
			if($this->lastResolveUnrejected === null){
				$this->reject(new AwaitException("Cannot yield Await::REJECT without yielding Await::RESOLVE first; they must be yielded in pairs"));
				return null;
			}
			return function() : void{
				$promise = $this->lastResolveUnrejected;
				$this->lastResolveUnrejected = null;
				$this->generator->send([$promise, "reject"]);
			};
		}

		$this->lastResolveUnrejected = null;

		if($current === self::RACE){
			if(count($this->promiseQueue) === 0){
				$this->reject(new AwaitException("Yielded Await::RACE when there is nothing racing"));
				return null;
			}

			$hasResult = 0; // 0 = all pending, 1 = one resolved, 2 = one rejected
			foreach($this->promiseQueue as $promise){
				if($promise->state === self::STATE_RESOLVED){
					$hasResult = 1;
					$result = $promise->resolved;
					break;
				}
				if($promise->state === self::STATE_REJECTED){
					$hasResult = 2;
					$result = $promise->rejected;
					break;
				}
			}

			if($hasResult !== 0){
				foreach($this->promiseQueue as $p){
					$p->cancelled = true;
				}
				$this->promiseQueue = [];
				assert(isset($result));
				if($hasResult === 1){
					return function() use ($result){
						$this->generator->send($result);
					};
				}
				assert($hasResult === 2);
				return function() use ($result){
					$this->generator->throw($result);
				};
			}

			$this->sleeping = true;
			return null;
		}

		if($current === self::ONCE || $current === self::ALL){
			if($current === self::ONCE && count($this->promiseQueue) !== 1){
				$this->reject(new AwaitException("Yielded Await::ONCE when the pending queue size is " . count($this->promiseQueue) . " != 1"));
				return null;
			}

			$results = [];

			// first check if nothing is immediately rejected
			foreach($this->promiseQueue as $promise){
				if($promise->state === self::STATE_REJECTED){
					foreach($this->promiseQueue as $p){
						$p->cancelled = true;
					}
					$this->promiseQueue = [];
					$ex = $promise->rejected;
					return function() use ($ex) : void{
						$this->generator->throw($ex);
					};
				}
			}

			foreach($this->promiseQueue as $promise){
				// if anything is pending, some others are pending and some others are resolved, but we will eventually get rejected/resolved from the pending promises
				if($promise->state === self::STATE_PENDING){
					$this->sleeping = true;
					return null;
				}
				assert($promise->state === self::STATE_RESOLVED);
				$results[] = $promise->resolved;
			}

			// all resolved
			$this->promiseQueue = [];
			return function() use ($current, $results) : void{
				$this->generator->send($current === self::ONCE ? $results[0] : $results);
			};
		}

		if($current instanceof Generator){
			if(!empty($this->promiseQueue)){
				$this->reject(new UnawaitedCallbackException("Yielding a generator"));
				return null;
			}

			$child = new AwaitChild($this);
			$await = Await::g2c($current, [$child, "resolve"], [$child, "reject"]);

			if($await->state === self::STATE_RESOLVED){
				$return = $await->resolved;
				return function() use ($return) : void{
					$this->generator->send($return);
				};
			}
			if($await->state === self::STATE_REJECTED){
				$ex = $await->rejected;
				return function() use ($ex) : void{
					$this->generator->throw($ex);
				};
			}

			$this->sleeping = true;
			$this->current = self::ONCE;
			$this->promiseQueue = [$await];
			return null;
		}

		$this->reject(new AwaitException("Unknown yield value"));
		return null;
	}

	public function recheckPromiseQueue(AwaitChild $changed) : void{
		assert($this->sleeping);
		if($this->current === self::RACE){
			foreach($this->promiseQueue as $p){
				$p->cancelled = true;
			}
			$this->promiseQueue = [];

			if($changed->state === self::STATE_REJECTED){
				$ex = $changed->rejected;
				$this->wakeupFlat(function() use ($ex) : void{
					$this->generator->throw($ex);
				});
			}else{
				$value = $changed->resolved;
				$this->wakeupFlat(function() use ($value) : void{
					$this->generator->send($value);
				});
			}
			return;
		}

		$current = $this->current;
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
				$this->wakeupFlat(function() use ($ex) : void{
					$this->generator->throw($ex);
				});
				return;
			}
			assert($promise->state === self::STATE_RESOLVED);
			$results[] = $promise->resolved;
		}
		// all resolved
		$this->promiseQueue = [];
		$this->wakeupFlat(function() use ($current, $results){
			$this->generator->send($current === self::ONCE ? $results[0] : $results);
		});
	}

	public function resolve($value) : void{
		if(!empty($this->promiseQueue)){
			$this->reject(new UnawaitedCallbackException("Resolution of await generator"));
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

		if(self::$debug){
			self::injectTrace($throwable, "Corrected generator stack trace", $this->lastTrace);
		}

		parent::reject($throwable);
		foreach($this->catches as $class => $onError){
			if($class === "" || is_a($throwable, $class)){
				$onError($throwable);
				return;
			}
		}
		throw new AwaitException("Unhandled async exception", 0, $throwable);
	}

	public function isSleeping() : bool{
		return $this->sleeping;
	}

	public function isUltimate() : bool{
		return $this->ultimate;
	}

	private static function injectTrace(Throwable $ex, string $middle, array $trace) : void{
		$ultimate = !isset($ex->_AwaitGenerator_injected_trace);
		/** @noinspection PhpUndefinedFieldInspection */
		$ex->_AwaitGenerator_injected_trace = true;

		if($ex instanceof Error){
			$class = new ReflectionClass(Error::class);
		}elseif($ex instanceof Exception){
			$class = new ReflectionClass(Exception::class);
		}else{
			return;
		}
		$prop = $class->getProperty("trace");
		$prop->setAccessible(true);
		$original = $prop->getValue($ex);
		$traceSeparator = $ultimate ? [
			[
				"file" => "\x1b[38;5;227mInternal\x1b[m",
				"line" => 0,
				"function" => $middle,
				"args" => [],
			],
		] : [];
		$new = array_merge($original, $traceSeparator, $trace);
		$prop->setValue($ex, $new);
	}
}
