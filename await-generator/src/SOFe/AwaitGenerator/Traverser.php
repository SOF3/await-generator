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
use Generator;
use Throwable;

/**
 * An adapter to convert an async function into an async iterator.
 *
 * The function can be written like a normal await-generator function.
 * When yielding a value is intended,
 * the user can run `yield $value => Await::VALUE;`
 * to stop and output the value.
 *
 * @template I
 */
final class Traverser{
	public const VALUE = "traverse.value";
	public const MAX_INTERRUPTS = 16;

	/** @var Generator */
	private $inner;

	public function __construct(Generator $inner){
		$this->inner = $inner;
	}

	/**
	 * @phpstan-param Closure(): Generator $closure
	 */
	public static function fromClosure(Closure $closure) : self{
		return new self($closure());
	}

	/**
	 * Creates a future that starts the next iteration of the underlying coroutine,
	 * and assigns the next yielded value to `$valueRef` and returns true.
	 *
	 * Returns false if there are no more values.
	 *
	 * @param-out I $valueRef
	 */
	public function next(mixed &$valueRef) : Generator{
		while($this->inner->valid()){
			$k = $this->inner->key();
			$v = $this->inner->current();

			if($v === self::VALUE){
				$this->inner->next();
				$valueRef = $k;
				return true;
			}else{
				// fallback to parent async context
				$this->inner->send(yield $k => $v);
			}
		}

		return false;
	}

	/**
	 * Asynchronously waits for all remaining values in the underlying iterator
	 * and collects them into a linear array.
	 *
	 * @return Generator<mixed, mixed, mixed, list<I>>
	 */
	public function collect() : Generator{
		$array = [];
		while(yield from $this->next($value)){
			$array[] = $value;
		}
		return $array;
	}

	/**
	 * Throw an exception into the underlying generator repeatedly
	 * so that all `finally` blocks can get asynchronously executed.
	 *
	 * If the underlying generator throws an exception not identical to `$ex`,
	 * this function will return the new exceptioin.
	 * Returns null if the underlying generator successfully terminated or throws.
	 *
	 * Throws `AwaitException` if `$attempts` throws were performed
	 * and the iterator is still executing.
	 *
	 * All values iterated during interruption are discarded.
	 */
	public function interrupt(Throwable $ex = null, int $attempts = self::MAX_INTERRUPTS) : Generator{
		$ex = $ex ?? InterruptException::get();
		for($i = 0; $i < $attempts; $i++){
			try{
				$this->inner->throw($ex);
				$hasMore = yield from $this->next($_);
				if(!$hasMore){
					return null;
				}
			}catch(Throwable $caught){
				if($caught === $ex){
					$caught = null;
				}
				return $caught;
			}
		}
		throw new AwaitException("Generator did not terminate after $attempts interrupts");
	}

	/**
	 * Returns the inner generator.
	 *
	 * Used to provide a shading-agnostic object so that it can be reconstructed in another shading namespace, e.g. for
	 * ```
	 * $namespace1Traverser = new Namespace1\AwaitGenerator\Traverser($this->iter());
	 * $namespace2Traverser = new Namespace2\AwaitGenerator\Traverser($namespace1Traverser->asGenerator());
	 * ```
	 * Then `$namespace1Traverser` and `$namespace2Traverser` are fully interchangeable wherever type check passes.
	 */
	public function asGenerator() : Generator {
		return $this->inner;
	}
}
