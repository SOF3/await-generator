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

use AssertionError;
use Closure;
use Generator;

/**
 * `Loading` is a class that represents an asynchronously loaded value.
 * Users with an instance of `Loading` can call `get` to wait for the loading process to complete.
 *
 * This is somewhat similar to the `Promise` class in JavaScript.
 *
 * @template T
 */
final class Loading{
	/** @var list<Closure(): void>|null */
	private ?array $onLoaded = [];
	private $value;

	/**
	 * @param Closure(): Generator<mixed, Await::RESOLVE|null|Await::RESOLVE_MULTI|Await::REJECT|Await::ONCE|Await::ALL|Await::RACE|Generator, mixed, T> $loader
	 */
	public function __construct(Closure $loader){
		Await::f2c(function() use($loader) {
			$this->value = yield from $loader();
			$onLoaded = $this->onLoaded;
			$this->onLoaded = null;

			if($onLoaded === null){
				throw new AssertionError("loader is called twice on the same object");
			}

			foreach($onLoaded as $closure){
				$closure();
			}
		});
	}

	/**
	 * @return Generator<mixed, Await::RESOLVE|null|Await::RESOLVE_MULTI|Await::REJECT|Await::ONCE|Await::ALL|Await::RACE|Generator, mixed, T>
	 */
	public function get() : Generator{
		if($this->onLoaded !== null){
			yield from Await::promise(function($resolve){
				$this->onLoaded[] = $resolve;
			});
		}

		return $this->value;
	}

	/**
	 * @template U
	 * @param U $default
	 * @return T|U
	 */
	public function getSync($default) {
		return $this->onLoaded === null ? $this->value : $default;
	}
}
