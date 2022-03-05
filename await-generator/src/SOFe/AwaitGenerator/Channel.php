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

use function array_shift;
use function count;
use Generator;

/**
 * A channel allows coroutines to communicate by sending and polling values in an FIFO stream.
 * @template T
 */
final class Channel{
	private EmptyChannelState|SendingChannelState|ReceivingChannelState $state;

	public function __construct(){
		$this->state = new EmptyChannelState;
	}

	/**
	 * @param T $value
	 */
	public function sendAndWait($value): Generator {
		if($this->state instanceof ReceivingChannelState){
			$receiver = array_shift($this->state->queue);
			if(count($this->state->queue) === 0){
				$this->state = new EmptyChannelState;
			}
			$receiver($value);
			return;
		}

		if($this->state instanceof EmptyChannelState){
			$this->state = new SendingChannelState;
		}

		yield from Await::promise(function($resolve) use($value){
			$this->state->queue[] = [$value, $resolve];
		});
	}

	public function sendNonBlock($value) : void{
		Await::g2c($this->sendAndWait($value));
	}

	public function receive(): Generator {
		if($this->state instanceof SendingChannelState){
			[$value, $sender] = array_shift($this->state->queue);
			if(count($this->state->queue) === 0){
				$this->state = new EmptyChannelState;
			}
			$sender();
			return $value;
		}

		if($this->state instanceof EmptyChannelState){
			$this->state = new ReceivingChannelState;
		}

		return yield from Await::promise(function($resolve){
			$this->state->queue[] = $resolve;
		});
	}

	public function getSendQueueSize() : int {
		if($this->state instanceof SendingChannelState){
			return count($this->state->queue);
		}

		return 0;
	}

	public function getReceiveQueueSize() : int {
		if($this->state instanceof ReceivingChannelState){
			return count($this->state->queue);
		}

		return 0;
	}
}
