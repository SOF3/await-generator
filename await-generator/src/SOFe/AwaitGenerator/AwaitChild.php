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

use Throwable;

/**
 * @template ParentT
 */
class AwaitChild extends PromiseState{
	/** @var Await<ParentT> */
	protected $await;


	/**
	 * @phpstan-param Await<ParentT> $await
	 */
	public function __construct(Await $await){
		$this->await = $await;
	}

	/**
	 * @param mixed $value
	 */
	public function resolve($value = null) : void{
		if($this->state !== self::STATE_PENDING){
			return; // nothing should happen if resolved/rejected multiple times
		}

		parent::resolve($value);
		if(!$this->cancelled && $this->await->isSleeping()){
			$this->await->recheckPromiseQueue($this);
		}
	}

	public function reject(Throwable $value) : void{
		if($this->state !== self::STATE_PENDING){
			return; // nothing should happen if resolved/rejected multiple times
		}

		parent::reject($value);
		if(!$this->cancelled && $this->await->isSleeping()){
			$this->await->recheckPromiseQueue($this);
		}
	}
}
