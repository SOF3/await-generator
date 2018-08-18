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

class GeneratorUtil{
	/**
	 * Returns a generator that yields nothing and returns $ret
	 *
	 * @param mixed $ret
	 *
	 * @return Generator
	 */
	public static function empty($ret = null) : Generator{
		yield from [];
		return $ret;
	}

	/**
	 * Returns a generator that yields nothing and throws $throwable
	 *
	 * @param Throwable $throwable
	 *
	 * @return Generator
	 * @throws Throwable
	 */
	public static function throw(Throwable $throwable) : Generator{
		yield from [];
		throw $throwable;
	}
}
