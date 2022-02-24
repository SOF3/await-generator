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
use PHPUnit\Framework\Assert;
use RuntimeException;

final class MockClock{
	/** @var array<int, list<Closure(): void>> */
	private array $ticks = [];
	private int $tick = 0;

	public function schedule(int $tick, Closure $closure) : void{
		if($this->tick >= $tick){
			throw new RuntimeException("Tick $tick is in the past");
		}

		if(!isset($this->ticks[$tick])){
			$this->ticks[$tick] = [];
		}

		$this->ticks[$tick][] = $closure;
	}

	public function nextTick(int $expectTick) : void{
		$this->tick++;
		Assert::assertSame($expectTick, $this->tick, "Test case has wrong clock counting");

		if(isset($this->ticks[$this->tick])){
			foreach($this->ticks[$this->tick] as $closure){
				$closure();
			}
		}
	}

	public function sleepUntil(int $tick) : Generator{
		$this->schedule($tick, yield Await::RESOLVE);
		yield Await::ONCE;
	}

	public function currentTick() : int{
		return $this->tick;
	}
}
