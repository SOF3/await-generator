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
use PHPUnit\Framework\TestCase;
use Throwable;
use function rand;

/**
 * @small
 */
class AwaitTest extends TestCase{
	/** @var callable[] */
	private static $later = [];

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

	public function testOneVoidImmediateResolveNull() : void{
		$rand = rand();
		self::assertImmediateResolve(function() use ($rand) : Generator{
			yield self::voidCallbackImmediate($rand, yield) => Await::ONCE;
		}, $rand);
	}

	public function testOneVoidImmediateResolve() : void{
		$rand = rand();
		self::assertImmediateResolve(function() use ($rand) : Generator{
			yield self::voidCallbackImmediate($rand, yield Await::RESOLVE) => Await::ONCE;
		}, $rand);
	}

	public function testOneVoidLaterResolve() : void{
		$rand = rand();
		self::assertLaterResolve(function() use ($rand) : Generator{
			yield self::voidCallbackLater($rand, yield Await::RESOLVE) => Await::ONCE;
		}, $rand);
	}

	public function testOneVoidImmediateReject() : void{
		$exception = new DummyException();
		self::assertImmediateReject(function() use ($exception) : Generator{
			yield; // unused
			yield self::voidCallbackImmediate($exception, yield Await::REJECT) => Await::ONCE;
		}, $exception);
	}

	public function testOneVoidLaterReject() : void{
		$exception = new DummyException();
		self::assertLaterReject(function() use ($exception) : Generator{
			yield self::voidCallbackLater($exception, yield Await::REJECT) => Await::ONCE;
		}, $exception);
	}

	private static function callGenerator(Closure $closure, &$resolveCalled, &$resolveValue, &$rejectCalled, &$rejectValue) : void{
		$resolveCalled = false;
		$resolveValue = null;
		$rejectCalled = false;
		$rejectValue = null;
		Await::f2c($closure, function($value) use (&$resolveCalled, &$resolveValue){
			$resolveCalled = true;
			$resolveValue = $value;
		}, function($value) use (&$rejectCalled, &$rejectValue){
			$rejectCalled = true;
			$rejectValue = $value;
		});
	}

	private static function assertImmediateResolve(Closure $closure, $expect) : void{
		self::callGenerator($closure, $resolveCalled, $resolveValue, $rejectCalled, $rejectValue);
		self::assertTrue($resolveCalled);
		self::assertFalse($rejectCalled);
		self::assertEquals($expect, $resolveValue);
	}

	private static function assertLaterResolve(Closure $closure, $expect) : void{
		self::callGenerator($closure, $resolveCalled, $resolveValue, $rejectCalled, $rejectValue);
		self::assertFalse($resolveCalled);
		self::assertFalse($rejectCalled);

		self::callLater();
		self::assertTrue($resolveCalled);
		self::assertFalse($rejectCalled);
		self::assertEquals($expect, $resolveValue);
	}

	private static function assertImmediateReject(Closure $closure, Throwable $object) : void{
		self::callGenerator($closure, $resolveCalled, $resolveValue, $rejectCalled, $rejectValue);
		self::assertFalse($resolveCalled);
		self::assertTrue($rejectCalled);
		self::assertEquals($object, $rejectValue);
	}

	private static function assertLaterReject(Closure $closure, Throwable $object) : void{
		self::callGenerator($closure, $resolveCalled, $resolveValue, $rejectCalled, $rejectValue);
		self::assertFalse($resolveCalled);
		if($rejectCalled){
			throw $object;
		}

		self::callLater();
		self::assertFalse($resolveCalled);
		self::assertTrue($rejectCalled);
		self::assertEquals($object, $rejectValue);
	}

	private static function callLater() : void{
		foreach(self::$later as $c){
			$c();
		}
		self::$later = [];
	}

	private static function voidCallbackImmediate($ret, callable $callback) : void{
		$callback($ret);
	}

	private static function voidCallbackLater($ret, callable $callback) : void{
		self::$later[] = function() use ($ret, $callback){
			$callback($ret);
		};
	}
}
