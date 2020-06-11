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

use Closure;
use Error;
use Exception;
use Generator;
use ReflectionClass;
use ReflectionGenerator;
use Throwable;
use function array_merge;
use function assert;
use function count;
use function is_a;
use function is_callable;

/**
 * @template T
 */
class Await extends PromiseState{
	public const RESOLVE = "resolve";
	public const RESOLVE_MULTI = [Await::RESOLVE];
	public const REJECT = "reject";
	public const ONCE = "once";
	public const ALL = "all";
	public const RACE = "race";

	/**
	 * @var Generator
	 * @phpstan-var Generator<mixed, Await::RESOLVE|null|Await::RESOLVE_MULTI|Await::REJECT|Await::ONCE|Await::ALL|Await::RACE|Generator>, mixed, T>
	 * */
	private $generator;
	/**
	 * @var callable|null
	 * @phpstan-var (callable(T): void)|null
	 */
	private $onComplete;
	/**
	 * @var callable[]
	 * @phpstan-var array<string, callable(Throwable): void>
	 */
	private $catches = [];
	/** @var bool */
	private $sleeping;
	/** @var PromiseState[] */
	private $promiseQueue = [];
	/** @var AwaitChild<T>|null */
	private $lastResolveUnrejected = null;
	/**
	 * @var string|string[]|null
	 * @phpstan-var Await::RESOLVE|null|Await::RESOLVE_MULTI|Await::REJECT|Await::ONCE|Await::ALL|Await::RACE|Generator<mixed, mixed, mixed, mixed>|null
	 */
	private $current = null;

	protected final function __construct(){
	}

	/**
	 * Converts a `Function<AwaitGenerator>` to a VoidCallback
	 *
	 * @param callable            $closure
	 * @phpstan-param callable(): Generator<mixed, Await::RESOLVE|null|Await::RESOLVE_MULTI|Await::REJECT|Await::ONCE|Await::ALL|Await::RACE|Generator, mixed, T> $closure
	 * @param callable|null       $onComplete
	 * @phpstan-param (callable(T): void)|null  $onComplete
	 * @param callable[]|callable $catches
	 * @phpstan-param array<string, callable(Throwable): void>|callable(Throwable): void $catches
	 *
	 * @return Await<T>
	 */
	public static function f2c(callable $closure, ?callable $onComplete = null, $catches = []) : Await{
		return self::g2c($closure(), $onComplete, $catches);
	}

	/**
	 * Converts an AwaitGenerator to a VoidCallback
	 *
	 * @param Generator           $generator
	 * @phpstan-param Generator<mixed, Await::RESOLVE|null|Await::RESOLVE_MULTI|Await::REJECT|Await::ONCE|Await::ALL|Await::RACE|Generator, mixed, T> $generator
	 * @param callable|null       $onComplete
	 * @phpstan-param (callable(T): void)|null  $onComplete
	 * @param callable[]|callable $catches
	 * @phpstan-param array<string, callable(Throwable): void>|callable(Throwable): void $catches
	 *
	 * @return Await<T>
	 */
	public static function g2c(Generator $generator, ?callable $onComplete = null, $catches = []) : Await{
		/** @var Await<T> $await */
		$await = new Await();
		$await->generator = $generator;
		$await->onComplete = $onComplete;
		if(is_callable($catches)){
			$await->catches = ["" => $catches];
		}else{
			$await->catches = $catches;
		}
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
	 * @template U
	 * @param Generator<mixed, Await::RESOLVE|null|Await::RESOLVE_MULTI|Await::REJECT|Await::ONCE|Await::ALL|Await::RACE|Generator, mixed, U>[] $generators
	 * @return Generator<mixed, Await::RESOLVE|null|Await::RESOLVE_MULTI|Await::REJECT|Await::ONCE|Await::ALL|Await::RACE|Generator, mixed, U[]>
	 */
	public static function all(array $generators) : Generator{
		if(count($generators) === 0){
			throw new AwaitException("Cannot await all on an empty array of generators");
		}

		foreach($generators as $k => $generator){
			$resolve = yield;
			$reject = yield self::REJECT;
			self::g2c($generator, static function($result) use($k, $resolve) : void{
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
	 * @template K
	 * @template U
	 * @param array<K, Generator<mixed, Await::RESOLVE|null|Await::RESOLVE_MULTI|Await::REJECT|Await::ONCE|Await::ALL|Await::RACE|Generator, mixed, U>> $generators
	 * @return Generator<mixed, Await::RESOLVE|null|Await::RESOLVE_MULTI|Await::REJECT|Await::ONCE|Await::ALL|Await::RACE|Generator, mixed, array{K, U}>
	 */
	public static function race(array $generators) : Generator{
		if(count($generators) === 0){
			throw new AwaitException("Cannot race an empty array of generators");
		}

		foreach($generators as $k => $generator){
			$resolve = yield;
			$reject = yield self::REJECT;
			self::g2c($generator, static function($result) use($k, $resolve) : void{
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
	 * @phpstan-param (callable(): void)|null $executor
	 *
	 * @internal This is implementation detail. Existence, signature and behaviour are semver-exempt.
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
	 * @phpstan-param callable(): void $executor
	 *
	 * @return (callable(): void)|null
	 */
	private function wakeup(callable $executor) : ?callable{
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
				$this->generator->send(Closure::fromCallable([$promise, "resolve"]));
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
				assert($promise !== null);
				$this->lastResolveUnrejected = null;
				$this->generator->send(Closure::fromCallable([$promise, "reject"]));
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
					return function() use ($result) : void{
						$this->generator->send($result);
					};
				}
				assert($hasResult === 2);
				return function() use ($result) : void{
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

	/**
	 * @phpstan-param AwaitChild<T> $changed
	 *
	 * @internal This is implementation detail. Existence, signature and behaviour are semver-exempt.
	 */
	public function recheckPromiseQueue(AwaitChild $changed) : void{
		assert($this->sleeping);
		if($this->current === self::ONCE){
			assert(count($this->promiseQueue) === 1);
		}

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
		$this->wakeupFlat(function() use ($current, $results) : void{
			$this->generator->send($current === self::ONCE ? $results[0] : $results);
		});
	}

	/**
	 * @param mixed $value
	 *
	 * @internal This is implementation detail. Existence, signature and behaviour are semver-exempt.
	 */
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

	/**
	 * @internal This is implementation detail. Existence, signature and behaviour are semver-exempt.
	 */
	public function reject(Throwable $throwable) : void{
		$this->sleeping = true;

		parent::reject($throwable);
		foreach($this->catches as $class => $onError){
			if($class === "" || is_a($throwable, $class)){
				$onError($throwable);
				return;
			}
		}
		throw new AwaitException("Unhandled async exception: {$throwable->getMessage()}", 0, $throwable);
	}

	/**
	 * @internal This is implementation detail. Existence, signature and behaviour are semver-exempt.
	 */
	public function isSleeping() : bool{
		return $this->sleeping;
	}
}
