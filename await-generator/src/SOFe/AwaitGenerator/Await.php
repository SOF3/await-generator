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
use Throwable;
use function assert;
use function count;
use function is_callable;

class Await extends PromiseState{
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
	/** @var AwaitChild[] */
	protected $promiseQueue = [];
	/** @var AwaitChild|null */
	protected $lastResolveUnrejected = null;
	protected $current;

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
		$this->current = $current = $this->generator->current() ?? self::RESOLVE;

		if($current === self::RESOLVE){
			return function() : void{
				$promise = new AwaitChild($this);
				$this->promiseQueue[] = $promise;
				$this->lastResolveUnrejected = $promise;
				$this->generator->send([$promise, "resolve"]);
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

		if($current === self::ONCE || $current === self::ALL){
			if($current === self::ONCE && count($this->promiseQueue) !== 1){
				$this->reject(new AwaitException("Yielded Await::ONCE when the pending queue size is " . count($this->promiseQueue) . " != 1"));
				return null;
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
					return function() use ($ex) : void{
						$this->generator->throw($ex);
					};
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

	public function recheckPromiseQueue() : void{
		assert($this->sleeping);
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
		parent::reject($throwable);
		foreach($this->catches as $class => $onError){
			if($class === "" || $throwable instanceof $class){
				$onError($throwable);
				return;
			}
		}
		throw new AwaitException("Unhandled async exception", 0, $throwable);
	}

	public function isSleeping() : bool{
		return $this->sleeping;
	}
}
