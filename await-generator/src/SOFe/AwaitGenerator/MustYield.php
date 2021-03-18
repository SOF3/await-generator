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
use Iterable;
use RuntimeException;

/**
 * @template R
 */
final class MustYield implements Iterable{
	public static function f(Closure $closure){
		return self::g($closure());
	}

	public static function g(Generator $generator){
		return new self($generator);
	}

	/** @var Generator */
	private $generator;
	/** @var Generator */
	private $usedAsIter;

	private function __construct(Generator $generator){
		$this->generator = $generator;
	}

	public function use() : Generator{
		if($this->generator === null){
			throw new RuntimeException("Attempt to use an async generator directly multiple times");
		}

		$this->usedAsIter = $this->generator;
		$this->generator = null;
		return $this->usedAsIter;
	}

	public function rewind() : void{
		$this->use();
	}

	public function key(){
		return $this->usedAsIter->key();
	}

	public function current(){
		return $this->usedAsIter->current();
	}

	public function next(){
		return $this->usedAsIter->next();
	}

	public function send($value){
		return $this->usedAsIter->send($value);
	}

	public function throw($ex){
		return $this->usedAsIter->throw($ex);
	}

	public function valid() : bool{
		return $this->usedAsIter->valid();
	}

	public function __destruct(){
		if($this->generator !== null){
			throw new RuntimeException("Unused async generator");
		}
	}
}
