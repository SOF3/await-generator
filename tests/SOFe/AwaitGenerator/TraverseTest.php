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
	private static function oneThree() : Generator{
		yield 1 => Traverser::VALUE;
		yield from GeneratorUtil::empty();
		yield 3 => Traverser::VALUE;
	}

	public function testArrayCollect(){
		Await::f2c(function() : Generator{
			$trav = new Traverser(self::oneThree());
			return yield from $trav->collect();
		}, function(array $array){
			self::assertSame([1, 3], $array);
		});
	}

	public function testNormalInterrupt(){
		Await::f2c(function() : Generator{
			$trav = new Traverser(self::oneThree());
			self::assertTrue(yield from $trav->next($value));
			self::assertSame(1, $value);

			return yield from $trav->interrupt();
		}, function($result) {
			self::assertSame(null, $result);
		});
	}

	public function testCaughtInterruptFinalized(){
		Await::f2c(function() : Generator{
			$trav = Traverser::fromClosure(function() : Generator{
				try{
					yield from GeneratorUtil::empty();
					yield 1 => Traverser::VALUE;
					yield from GeneratorUtil::empty();
					yield 2 => Traverser::VALUE;
				}finally{
					yield 3 => Traverser::VALUE;
					yield from GeneratorUtil::empty();
					yield 4 => Traverser::VALUE;
				}
			});
			self::assertTrue(yield from $trav->next($value));
			self::assertSame(1, $value);

			return yield from $trav->interrupt();
		}, function($result) {
			self::assertSame(null, $result);
		});
	}

	public function testLoopingInterruptCatch(){
		Await::f2c(function() : Generator{
			$trav = Traverser::fromClosure(function() : Generator{
				while(true){
					try{
						yield from GeneratorUtil::empty();
						yield 1 => Traverser::VALUE;
						yield from GeneratorUtil::empty();
						yield 2 => Traverser::VALUE;
					}catch(\Exception $ex){
						yield from GeneratorUtil::empty();
						yield 3 => Traverser::VALUE;
					}
				}
			});
			self::assertTrue(yield from $trav->next($value));
			self::assertSame(1, $value);

			return yield from $trav->interrupt();
		}, function() {
			self::assertFalse("unreachable");
		}, function(\Exception $ex) {
			self::assertEquals("Generator did not terminate after 16 interrupts", $ex->getMessage());
		});
	}

	/**
	 * Test whether the inner-generator of a traverser can communicate with
	 * the await-generator's runtime properly through `yield`.
	 * 
	 * As a traverser should not handle any `yield` that
	 * does not have {@link Traverser::VALUE} as its value.
	 * 
	 * Otherwise, await-generator's core functionalities might not
	 * work correctly, such as {@link Await::promise()}.
	 */
	public function testYieldBridging(){
		Await::f2c(function() : Generator{
			$trav = Traverser::fromClosure(function() : Generator{
				for ($i = 0; $i < 2; $i++) {
					$got = yield from Await::promise(function ($resolve) use (&$i) : void {
						$resolve($i);
					});
					yield $got => Traverser::VALUE;
				}
			});

			for ($expect = 0; $expect < 2; $expect++) {
				self::assertTrue(yield from $trav->next($value));
				self::assertSame($expect, $value);
			}
			self::assertFalse(yield from $trav->next($value));
		});
		
	}
}
