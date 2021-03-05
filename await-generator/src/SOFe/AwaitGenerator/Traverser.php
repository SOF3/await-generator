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
use function func_num_args;

/**
 * An adapter to convert an async function into an async iterator.
 *
 * The function can be written like a normal await-generator function.
 * When yielding a value is intended,
 * the user can run `yield $value => Await::VALUE;`
 * to stop and output the value.
 */
final class Traverser {
	public const VALUE = "traverse.value";

	/** @var Generator */
	private $inner;

	public function __construct(Generator $inner) {
		$this->inner = $inner;
	}

	/**
	 * Creates a future that starts the next iteration of the underlying coroutine,
	 * and assigns the next yielded value to `$valueRef` and returns true.
	 *
	 * Returns false if there are no more values.
	 */
	public function next(&$valueRef) : Generator {
		while($this->inner->valid()) {
			$k = $this->inner->key();
			$v = $this->inner->current();
			$this->inner->next();

			if($v === self::VALUE) {
				$valueRef = $k;
				return true;
			} else {
				// fallback to parent async context
				yield $k => $v;
			}
		}

		return false;
	}

	/**
	 * Asynchronously waits for all remaining values in the underlying iterator
	 * and collects them into a linear array.
	 */
	public function collect() : Generator {
		$array = [];
		while(yield $this->next($value)) {
			$array[] = $value;
		}
		return $array;
	}
}
