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
use function array_shift;

/**
 * @small
 */
class AwaitTest extends TestCase{
	/** @var callable[] */
	private static $later = [];

	public function testEmptyGeneratorCreation() : void{
		$rand = 0xABADBABE;
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

	public function testEmpty() : void{
		$rand = 0xB16B00B5;
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

	public function testBadYield() : void{
		Await::f2c(function() : Generator{
			yield "(some invalid value)";
		}, function(){
			self::assertTrue(false, "unexpected resolve call");
		}, function($ex) : void{
			self::assertInstanceOf(AwaitException::class, $ex);
			/** @var AwaitException $ex */
			self::assertEquals("Unknown yield value", $ex->getMessage());
		});
	}

	public function testOneUnresolved() : void{
		Await::f2c(function() : Generator{
			yield;
		}, function(){
			self::assertTrue(false, "unexpected resolve call");
		}, function($ex) : void{
			self::assertInstanceOf(UnawaitedCallbackException::class, $ex);
			/** @var AwaitException $ex */
			self::assertEquals("Resolution of await generator is disallowed when Await::RESOLVE or Await::REJECT was yielded but is not awaited through Await::ONCE, Await::ALL or Await::RACE", $ex->getMessage());
		});
	}

	public function testRejectOnly() : void{
		Await::f2c(function() : Generator{
			yield Await::REJECT;
		}, function(){
			self::assertTrue(false, "unexpected resolve call");
		}, function($ex) : void{
			self::assertInstanceOf(AwaitException::class, $ex);
			/** @var AwaitException $ex */
			self::assertEquals("Cannot yield Await::REJECT without yielding Await::RESOLVE first; they must be yielded in pairs", $ex->getMessage());
		});
	}

	public function testDoubleReject() : void{
		$firstRejectOk = false;
		Await::f2c(function() use (&$firstRejectOk) : Generator{
			yield;
			yield Await::REJECT;
			$firstRejectOk = true;
			yield Await::REJECT;
		}, function(){
			self::assertTrue(false, "unexpected resolve call");
		}, function($ex) : void{
			self::assertInstanceOf(AwaitException::class, $ex);
			/** @var AwaitException $ex */
			self::assertEquals("Cannot yield Await::REJECT without yielding Await::RESOLVE first; they must be yielded in pairs", $ex->getMessage());
		});

		self::assertTrue($firstRejectOk, "first paired rejection failed");
	}

	public function testOnceAtZero() : void{
		Await::f2c(function() : Generator{
			yield Await::ONCE;
		}, function(){
			self::assertTrue(false, "unexpected resolve call");
		}, function($ex) : void{
			self::assertInstanceOf(AwaitException::class, $ex);
			/** @var AwaitException $ex */
			self::assertEquals("Yielded Await::ONCE when the pending queue size is 0 != 1", $ex->getMessage());
		});
	}

	public function testOnceAtTwo() : void{
		Await::f2c(function() : Generator{
			yield;
			yield;
			yield Await::ONCE;
		}, function(){
			self::assertTrue(false, "unexpected resolve call");
		}, function($ex) : void{
			self::assertInstanceOf(AwaitException::class, $ex);
			/** @var UnawaitedCallbackException $ex */
			self::assertEquals("Yielded Await::ONCE when the pending queue size is 2 != 1", $ex->getMessage());
		});
	}

	public function testVoidImmediateResolveVoid() : void{
		$rand = 0xCAFEF33D;
		self::assertImmediateResolve(function() use ($rand) : Generator{
			yield self::voidCallbackImmediate($rand, yield) => Await::ONCE;
		}, null);
	}

	public function testVoidLaterResolveVoid() : void{
		$rand = 0xCAFEF33D;
		self::assertLaterResolve(function() use ($rand) : Generator{
			yield self::voidCallbackLater($rand, yield) => Await::ONCE;
		}, null);
	}

	public function testVoidImmediateResolveNull() : void{
		$rand = 0xCAFEFEED;
		self::assertImmediateResolve(function() use ($rand) : Generator{
			return yield self::voidCallbackImmediate($rand, yield) => Await::ONCE;
		}, $rand);
	}

	public function testVoidImmediateResolve() : void{
		$rand = 0xDEADBEEF;
		self::assertImmediateResolve(function() use ($rand) : Generator{
			return yield self::voidCallbackImmediate($rand, yield Await::RESOLVE) => Await::ONCE;
		}, $rand);
	}

	public function testVoidLaterResolve() : void{
		$rand = 0xFEEDFACE;
		self::assertLaterResolve(function() use ($rand) : Generator{
			return yield self::voidCallbackLater($rand, yield Await::RESOLVE) => Await::ONCE;
		}, $rand);
	}

	public function testVoidImmediateReject() : void{
		$exception = new DummyException();
		self::assertImmediateReject(function() use ($exception) : Generator{
			yield Await::RESOLVE; // unused
			yield self::voidCallbackImmediate($exception, yield Await::REJECT) => Await::ONCE;
		}, $exception);
	}

	public function testVoidLaterReject() : void{
		$exception = new DummyException();
		self::assertLaterReject(function() use ($exception) : Generator{
			yield Await::RESOLVE; // unused
			yield self::voidCallbackLater($exception, yield Await::REJECT) => Await::ONCE;
		}, $exception);
	}

	public function testVoidOnceImmediateResolveImmediateResolve() : void{
		$rand = [0x12345678, 0x4bcd3f96];
		self::assertImmediateResolve(function() use ($rand) : Generator{
			$first = yield self::voidCallbackImmediate($rand[0], yield Await::RESOLVE) => Await::ONCE;
			$second = yield self::voidCallbackImmediate($rand[1], yield Await::RESOLVE) => Await::ONCE;
			return [$first, $second];
		}, $rand);
	}

	public function testVoidOnceImmediateResolveLaterResolve() : void{
		$rand = [0x12345678, 0x4bcd3f96];
		self::assertLaterResolve(function() use ($rand) : Generator{
			$first = yield self::voidCallbackImmediate($rand[0], yield Await::RESOLVE) => Await::ONCE;
			$second = yield self::voidCallbackLater($rand[1], yield Await::RESOLVE) => Await::ONCE;
			return [$first, $second];
		}, $rand);
	}

	public function testVoidOnceLaterResolveImmediateResolve() : void{
		$rand = [0x12345678, 0x4bcd3f96];
		self::assertLaterResolve(function() use ($rand) : Generator{
			$first = yield self::voidCallbackLater($rand[0], yield Await::RESOLVE) => Await::ONCE;
			$second = yield self::voidCallbackImmediate($rand[1], yield Await::RESOLVE) => Await::ONCE;
			return [$first, $second];
		}, $rand);
	}

	public function testVoidOnceLaterResolveLaterResolve() : void{
		$rand = [0x12345678, 0x4bcd3f96];
		self::assertLaterResolve(function() use ($rand) : Generator{
			$first = yield self::voidCallbackLater($rand[0], yield Await::RESOLVE) => Await::ONCE;
			$second = yield self::voidCallbackLater($rand[1], yield Await::RESOLVE) => Await::ONCE;
			return [$first, $second];
		}, $rand);
	}

	public function testVoidAllImmediateResolveImmediateResolve() : void{
		$rand = [0x12345678, 0x4bcd3f96];
		self::assertImmediateResolve(function() use ($rand) : Generator{
			self::voidCallbackImmediate($rand[0], yield Await::RESOLVE);
			self::voidCallbackImmediate($rand[1], yield Await::RESOLVE);
			return yield Await::ALL;
		}, $rand);
	}

	public function testVoidAllImmediateResolveLaterResolve() : void{
		$rand = [0x12345678, 0x4bcd3f96];
		self::assertLaterResolve(function() use ($rand) : Generator{
			self::voidCallbackImmediate($rand[0], yield Await::RESOLVE);
			self::voidCallbackLater($rand[1], yield Await::RESOLVE);
			return yield Await::ALL;
		}, $rand);
	}

	public function testVoidAllLaterResolveImmediateResolve() : void{
		$rand = [0x12345678, 0x4bcd3f96];
		self::assertLaterResolve(function() use ($rand) : Generator{
			self::voidCallbackLater($rand[0], yield Await::RESOLVE);
			self::voidCallbackImmediate($rand[1], yield Await::RESOLVE);
			return yield Await::ALL;
		}, $rand);
	}

	public function testVoidAllLaterResolveLaterResolve() : void{
		$rand = [0x12345678, 0x4BCD3F96];
		self::assertLaterResolve(function() use ($rand) : Generator{
			self::voidCallbackLater($rand[0], yield Await::RESOLVE);
			self::voidCallbackLater($rand[1], yield Await::RESOLVE);
			return yield Await::ALL;
		}, $rand);
	}

	public function testGeneratorWithoutCollect() : void{
		Await::f2c(function(){
			yield;
			yield self::generatorVoidImmediate();
		}, function() : void{
			self::assertTrue(false, "unexpected resolve call");
		}, function($ex) : void{
			self::assertInstanceOf(UnawaitedCallbackException::class, $ex);
			/** @var AwaitException $ex */
			self::assertEquals("Yielding a generator is disallowed when Await::RESOLVE or Await::REJECT was yielded but is not awaited through Await::ONCE, Await::ALL or Await::RACE", $ex->getMessage());
		});
	}

	public function testGeneratorImmediateResolve() : void{
		$rand = 0xD3AD8EEF;
		self::assertImmediateResolve(function() use ($rand) : Generator{
			return yield GeneratorUtil::empty($rand);
		}, $rand);
	}

	public function testGeneratorLaterResolve() : void{
		$rand = 0xD3AD8EEF;
		self::assertLaterResolve(function() use ($rand) : Generator{
			return yield self::generatorReturnLater($rand);
		}, $rand);
	}

	public function testGeneratorImmediateReject() : void{
		$ex = new DummyException();
		self::assertImmediateReject(function() use ($ex) : Generator{
			yield GeneratorUtil::throw($ex);
		}, $ex);
	}

	public function testGeneratorLaterReject() : void{
		$ex = new DummyException();
		self::assertLaterReject(function() use ($ex) : Generator{
			yield self::generatorThrowLater($ex);
		}, $ex);
	}

	public function testGeneratorImmediateResolveVoid() : void{
		self::assertImmediateResolve(function() : Generator{
			yield self::generatorVoidImmediate();
		}, null);
	}

	public function testGeneratorLaterResolveVoid() : void{
		self::assertLaterResolve(function() : Generator{
			yield self::generatorVoidLater();
		}, null);
	}


	private static function assertImmediateResolve(Closure $closure, $expect) : void{
		$resolveCalled = false;
		Await::f2c($closure, function($actual) use ($expect, &$resolveCalled) : void{
			$resolveCalled = true;
			self::assertEquals($expect, $actual);
		}, function(Throwable $ex) : void{
			self::assertTrue(false, "unexpected reject call: " . $ex->getMessage());
		});
		self::assertTrue($resolveCalled, "resolve was not called");
	}

	private static function assertLaterResolve(Closure $closure, $expect) : void{
		$laterCalled = false;
		$resolveCalled = false;
		Await::f2c($closure, function($actual) use ($expect, &$laterCalled, &$resolveCalled) : void{
			self::assertTrue($laterCalled, "resolve called before callLater()");
			$resolveCalled = true;
			self::assertEquals($expect, $actual);
		}, function(Throwable $ex) : void{
			self::assertTrue(false, "unexpected reject call: " . $ex->getMessage());
		});

		$laterCalled = true;
		self::callLater();
		self::assertTrue($resolveCalled, "resolve was not called");
	}

	private static function assertImmediateReject(Closure $closure, Throwable $object) : void{
		$rejectCalled = false;
		Await::f2c($closure, function() : void{
			self::assertTrue(false, "unexpected resolve call");
		}, function(Throwable $ex) use ($object, &$rejectCalled) : void{
			$rejectCalled = true;
			self::assertEquals($object, $ex);
		});
		self::assertTrue($rejectCalled, "reject was not called");
	}

	private static function assertLaterReject(Closure $closure, Throwable $object) : void{
		$laterCalled = false;
		$rejectCalled = false;
		Await::f2c($closure, function() : void{
			self::assertTrue(false, "unexpected reject call");
		}, function(Throwable $ex) use ($object, &$laterCalled, &$rejectCalled) : void{
			self::assertTrue($laterCalled, "reject called before callLater(): " . $ex->getMessage());
			$rejectCalled = true;
			self::assertEquals($object, $ex);
		});

		$laterCalled = true;
		self::callLater();
		self::assertTrue($rejectCalled, "reject was not called");
	}

	private static function callLater() : void{
		while(($c = array_shift(self::$later)) !== null){
			$c();
		}
	}

	private static function voidCallbackImmediate($ret, callable $callback) : void{
		$callback($ret);
	}

	private static function voidCallbackLater($ret, callable $callback) : void{
		self::$later[] = function() use ($ret, $callback){
			$callback($ret);
		};
	}

	private static function generatorReturnLater($ret) : Generator{
		return yield self::voidCallbackLater($ret, yield) => Await::ONCE;
	}

	private static function generatorThrowLater(Throwable $ex) : Generator{
		yield self::voidCallbackLater(null, yield) => Await::ONCE;
		throw $ex;
	}

	private static function generatorVoidImmediate() : Generator{
		if(false){
			yield;
		}
	}

	private static function generatorVoidLater() : Generator{
		yield self::voidCallbackLater(null, yield) => Await::ONCE;
	}
}
