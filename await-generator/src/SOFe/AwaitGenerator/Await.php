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

final class Await{
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
				($this->onComplete)(...(array) $ret);
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
