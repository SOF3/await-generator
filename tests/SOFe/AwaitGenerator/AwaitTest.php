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
use function rand;

/**
 * @small
 */
class AwaitTest extends TestCase{
	public function testEmptyGeneratorCreation() : void{
		$rand = rand();
		$generator = GeneratorUtil::empty($rand);
		$generator->rewind();
		self::assertFalse($generator->valid());
		self::assertEquals($rand, $generator->getReturn());
	}

	public function testThrowsGeneratorCreation() : void{
		$exception = new DummyException();
		$generator = GeneratorUtil::throw($exception);
		$this->expectExceptionObject($exception);
		$generator->rewind();
	}

	/**
	 * @depends testEmptyGeneratorCreation
	 */
	public function testEmpty() : void{
		$rand = rand();
		$generator = GeneratorUtil::empty($rand);
		$resolveCalled = false;
		$rejectCalled = false;
		Await::g2c($generator, function($arg) use (&$resolveCalled, &$resolveValue) : void{
			$resolveCalled = true;
			$resolveValue = $arg;
		}, function() use (&$rejectCalled) : void{
			$rejectCalled = true;
		});
		self::assertTrue($resolveCalled);
		self::assertFalse($rejectCalled);
		self::assertEquals($rand, $resolveValue);
	}

	/**
	 * @depends testThrowsGeneratorCreation
	 */
	public function testImmediateThrow() : void{
		$exception = new DummyException();
		$generator = GeneratorUtil::throw($exception);
		$resolveCalled = false;
		Await::g2c($generator, function() use (&$resolveCalled) : void{
			$resolveCalled = true;
		}, function($ex) use (&$rejectCalled, &$rejectValue) : void{
			$rejectCalled = true;
			$rejectValue = $ex;
		});
		self::assertFalse($resolveCalled);
		self::assertTrue($rejectCalled);
		self::assertEquals($exception, $rejectValue);
	}

	public function testOneUnresolved() : void{
		$thrown = false;
		Await::f2c(function() : Generator{
			yield;
		}, null, [
			UnresolvedCallbackException::class => function() use (&$thrown){
				$thrown = true;
			}
		]);
		self::assertTrue($thrown, "UnresolvedCallbackException was not thrown");
	}
}
