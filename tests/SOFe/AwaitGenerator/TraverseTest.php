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
use PHPUnit\Framework\TestCase;
use Throwable;
use function array_shift;
use function get_class;

/**
 * @small
 */
class TraverseTest extends TestCase{
	private function oneThree() : Generator{
		yield 1 => Traverser::VALUE;
		yield GeneratorUtils::empty();
		yield 3 => Traverser::VALUE;
	}

	public function testArrayCollect(){
		Await::f2c(function() : Generator{
			$trav = new Traverser($this->oneThree());
			return yield $trav->collect();
		}, function(array $array){
			self::assertSame([1, 3], $array);
		});
	}
}
