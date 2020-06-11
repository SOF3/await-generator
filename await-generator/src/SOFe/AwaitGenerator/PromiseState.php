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
use function assert;

abstract class PromiseState{
	public const STATE_PENDING = 0;
	public const STATE_RESOLVED = 1;
	public const STATE_REJECTED = 2;

	/** @var int */
	protected $state = self::STATE_PENDING;
	/** @var mixed */
	protected $resolved;
	/** @var Throwable */
	protected $rejected;

	/** @var bool  */
	protected $cancelled = false;

	/**
	 * @param mixed $value
	 */
	public function resolve($value) : void{
		assert($this->state === self::STATE_PENDING);

		$this->state = self::STATE_RESOLVED;
		$this->resolved = $value;
	}

	public function reject(Throwable $value) : void{
		assert($this->state === self::STATE_PENDING);

		$this->state = self::STATE_REJECTED;
		$this->rejected = $value;
	}
}
