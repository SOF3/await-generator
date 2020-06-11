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
use function get_class;

// # How to write unit tests?
// Call `assert(Immediate|Later)(Resolve|Reject)`
// with an await-generator closure and an expected return value
// to assert that the closure should resolve/reject immediately/later
// with the expected value.
//
// Call `voidCallbackImmediate` or `voidCallbackLater`
// to imitate callback async functions that would call the callback
// immediately or resolved.
//
// "Immediate" means that the callback is called asap.
// In the `voidCallback` case, the function simply calls the callback directly.
// In the `assert` case, this means the callback is expected to resolve
// just during the `Await::f2c` call.
//
// "Later" means that the callback is queued in the static variable `AwaitTest::$later`.
// This queue of callbacks will be called by `assertLater` after `Await::f2c` has been called.
// Callbacks are asserted to not resolve before this queue is dispatched.

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

	public function testUnhandledImmediateReject() : void{
		$ex = new DummyException();
		$generator = GeneratorUtil::throw($ex);
		try{
			Await::g2c($generator, function() : void{
				self::assertTrue(false, "unexpected resolve call");
			});
		}catch(AwaitException $e){
			self::assertEquals("Unhandled async exception: {$ex->getMessage()}", $e->getMessage());
			self::assertEquals($ex, $e->getPrevious());
		}
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

	public function testRaceAtZero() : void{
		Await::f2c(function() : Generator{
			yield Await::RACE;
		}, function(){
			self::assertTrue(false, "unexpected resolve call");
		}, function($ex) : void{
			self::assertInstanceOf(AwaitException::class, $ex);
			/** @var UnawaitedCallbackException $ex */
			self::assertEquals("Yielded Await::RACE when there is nothing racing", $ex->getMessage());
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

	public function testVoidImmediateResolveMulti() : void{
		$rand = [0xDEADBEEF, 0xFEEDFACE];
		$resolveCalled = false;

		$async = function(callable $callback) use($rand) : void {
			$callback($rand[0], $rand[1]);
		};

		Await::f2c(function() use($async) : Generator{
			return yield $async(yield Await::RESOLVE_MULTI) => Await::ONCE;
		}, function($actual) use ($rand, &$resolveCalled) : void{
			$resolveCalled = true;
			self::assertEquals($rand, $actual);
		}, function(Throwable $ex) : void{
			self::assertTrue(false, "unexpected reject call: " . $ex->getMessage());
		});
		self::assertTrue($resolveCalled, "resolve was not called");
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

	public function testVoidAllImmediateResolveImmediateReject() : void{
		$rand = 0x12345678;
		$ex = new DummyException();
		self::assertImmediateReject(function() use ($rand, $ex) : Generator{
			self::voidCallbackImmediate($rand, yield Await::RESOLVE);
			yield Await::RESOLVE;
			self::voidCallbackImmediate($ex, yield Await::REJECT);
			return yield Await::ALL;
		}, $ex);
	}

	public function testVoidAllImmediateResolveLaterResolve() : void{
		$rand = [0x12345678, 0x4bcd3f96];
		self::assertLaterResolve(function() use ($rand) : Generator{
			self::voidCallbackImmediate($rand[0], yield Await::RESOLVE);
			self::voidCallbackLater($rand[1], yield Await::RESOLVE);
			return yield Await::ALL;
		}, $rand);
	}

	public function testVoidAllImmediateResolveLaterReject() : void{
		$rand = 0x12345678;
		$ex = new DummyException();
		self::assertLaterReject(function() use ($rand, $ex) : Generator{
			self::voidCallbackImmediate($rand, yield Await::RESOLVE);
			yield Await::RESOLVE;
			self::voidCallbackLater($ex, yield Await::REJECT);
			return yield Await::ALL;
		}, $ex);
	}

	public function testVoidAllImmediateRejectImmediateResolve() : void{
		$ex = new DummyException();
		$rand = 0x1234567B;
		self::assertImmediateReject(function() use ($ex, $rand) : Generator{
			yield Await::RESOLVE;
			self::voidCallbackImmediate($ex, yield Await::REJECT);
			self::voidCallbackImmediate($rand, yield Await::RESOLVE);
			return yield Await::ALL;
		}, $ex);
	}

	public function testVoidAllImmediateRejectImmediateReject() : void{
		$ex = new DummyException();
		$ex2 = new DummyException();
		self::assertImmediateReject(function() use ($ex, $ex2) : Generator{
			yield Await::RESOLVE;
			self::voidCallbackImmediate($ex, yield Await::REJECT);
			yield Await::RESOLVE;
			self::voidCallbackImmediate($ex2, yield Await::REJECT);
			return yield Await::ALL;
		}, $ex);
	}

	public function testVoidAllImmediateRejectLaterResolve() : void{
		$ex = new DummyException();
		$rand = 0x1234567B;
		self::assertImmediateReject(function() use ($ex, $rand) : Generator{
			yield Await::RESOLVE;
			self::voidCallbackImmediate($ex, yield Await::REJECT);
			self::voidCallbackLater($rand, yield Await::RESOLVE);
			return yield Await::ALL;
		}, $ex);
	}

	public function testVoidAllImmediateRejectLaterReject() : void{
		$ex = new DummyException();
		$ex2 = new DummyException();
		self::assertImmediateReject(function() use ($ex, $ex2) : Generator{
			yield Await::RESOLVE;
			self::voidCallbackImmediate($ex, yield Await::REJECT);
			yield Await::RESOLVE;
			self::voidCallbackLater($ex2, yield Await::REJECT);
			return yield Await::ALL;
		}, $ex);
	}

	public function testVoidAllLaterResolveImmediateResolve() : void{
		$rand = [0x12345678, 0x4bcd3f96];
		self::assertLaterResolve(function() use ($rand) : Generator{
			self::voidCallbackLater($rand[0], yield Await::RESOLVE);
			self::voidCallbackImmediate($rand[1], yield Await::RESOLVE);
			return yield Await::ALL;
		}, $rand);
	}

	public function testVoidAllLaterResolveImmediateReject() : void{
		$rand = 0x12345678;
		$ex = new DummyException();
		self::assertImmediateReject(function() use ($rand, $ex) : Generator{
			self::voidCallbackLater($rand, yield Await::RESOLVE);
			yield Await::RESOLVE;
			self::voidCallbackImmediate($ex, yield Await::REJECT);
			return yield Await::ALL;
		}, $ex);
	}

	public function testVoidAllLaterResolveLaterResolve() : void{
		$rand = [0x12345678, 0x4BCD3F96];
		self::assertLaterResolve(function() use ($rand) : Generator{
			self::voidCallbackLater($rand[0], yield Await::RESOLVE);
			self::voidCallbackLater($rand[1], yield Await::RESOLVE);
			return yield Await::ALL;
		}, $rand);
	}

	public function testVoidAllLaterResolveLaterReject() : void{
		$rand = 0x12345678;
		$ex = new DummyException();
		self::assertLaterReject(function() use ($rand, $ex) : Generator{
			self::voidCallbackLater($rand, yield Await::RESOLVE);
			yield Await::RESOLVE;
			self::voidCallbackLater($ex, yield Await::REJECT);
			return yield Await::ALL;
		}, $ex);
	}

	public function testVoidAllLaterRejectImmediateReject() : void{
		$ex = new DummyException();
		$ex2 = new DummyException();
		self::assertImmediateReject(function() use ($ex, $ex2) : Generator{
			yield Await::RESOLVE;
			self::voidCallbackLater($ex, yield Await::REJECT);
			yield Await::RESOLVE;
			self::voidCallbackImmediate($ex2, yield Await::REJECT);
			return yield Await::ALL;
		}, $ex2);
	}

	public function testVoidAllLaterRejectImmediateResolve() : void{
		$ex = new DummyException();
		$rand = 0x1234567B;
		self::assertLaterReject(function() use ($ex, $rand) : Generator{
			yield Await::RESOLVE;
			self::voidCallbackLater($ex, yield Await::REJECT);
			self::voidCallbackImmediate($rand, yield Await::RESOLVE);
			return yield Await::ALL;
		}, $ex);
	}

	public function testVoidAllLaterRejectLaterResolve() : void{
		$ex = new DummyException();
		$rand = 0x1234567B;
		self::assertLaterReject(function() use ($ex, $rand) : Generator{
			yield Await::RESOLVE;
			self::voidCallbackLater($ex, yield Await::REJECT);
			self::voidCallbackLater($rand, yield Await::RESOLVE);
			return yield Await::ALL;
		}, $ex);
	}

	public function testVoidAllLaterRejectLaterReject() : void{
		$ex = new DummyException();
		$ex2 = new DummyException();
		self::assertLaterReject(function() use ($ex, $ex2) : Generator{
			yield Await::RESOLVE;
			self::voidCallbackLater($ex, yield Await::REJECT);
			yield Await::RESOLVE;
			self::voidCallbackLater($ex2, yield Await::REJECT);
			return yield Await::ALL;
		}, $ex);
	}


	public function testVoidRaceImmediateResolveImmediateResolve() : void{
		$rand = [0x12345678, 0x4bcd3f96];
		self::assertImmediateResolve(function() use ($rand) : Generator{
			self::voidCallbackImmediate($rand[0], yield Await::RESOLVE);
			self::voidCallbackImmediate($rand[1], yield Await::RESOLVE);
			return yield Await::RACE;
		}, $rand[0]);
	}

	public function testVoidRaceImmediateResolveImmediateReject() : void{
		$rand = 0x12345678;
		$ex = new DummyException();
		self::assertImmediateResolve(function() use ($rand, $ex) : Generator{
			self::voidCallbackImmediate($rand, yield Await::RESOLVE);
			yield Await::RESOLVE; // start a new promise
			self::voidCallbackImmediate($ex, yield Await::REJECT);
			return yield Await::RACE;
		}, $rand);
	}

	public function testVoidRaceImmediateResolveLaterResolve() : void{
		$rand = [0x12345678, 0x4bcd3f96];
		self::assertImmediateResolve(function() use ($rand) : Generator{
			self::voidCallbackImmediate($rand[0], yield Await::RESOLVE);
			self::voidCallbackLater($rand[1], yield Await::RESOLVE);
			return yield Await::RACE;
		}, $rand[0]);
	}

	public function testVoidRaceImmediateResolveLaterReject() : void{
		$rand = 0x12345678;
		$ex = new DummyException();
		self::assertImmediateResolve(function() use ($rand, $ex) : Generator{
			self::voidCallbackImmediate($rand, yield Await::RESOLVE);
			self::voidCallbackLater($ex, yield Await::REJECT);
			return yield Await::RACE;
		}, $rand);
	}

	public function testVoidRaceImmediateRejectImmediateResolve() : void{
		$ex = new DummyException();
		$rand = 0x1234567B;
		self::assertImmediateReject(function() use ($ex, $rand) : Generator{
			yield Await::RESOLVE;
			self::voidCallbackImmediate($ex, yield Await::REJECT);
			self::voidCallbackImmediate($rand, yield Await::RESOLVE);
			return yield Await::RACE;
		}, $ex);
	}

	public function testVoidRaceImmediateRejectImmediateReject() : void{
		$ex = new DummyException();
		$ex2 = new DummyException();
		self::assertImmediateReject(function() use ($ex, $ex2) : Generator{
			yield Await::RESOLVE;
			self::voidCallbackImmediate($ex, yield Await::REJECT);
			yield Await::RESOLVE;
			self::voidCallbackImmediate($ex2, yield Await::REJECT);
			return yield Await::RACE;
		}, $ex);
	}

	public function testVoidRaceImmediateRejectLaterResolve() : void{
		$ex = new DummyException();
		$rand = 0x1234567B;
		self::assertImmediateReject(function() use ($ex, $rand) : Generator{
			yield Await::RESOLVE;
			self::voidCallbackImmediate($ex, yield Await::REJECT);
			self::voidCallbackLater($rand, yield Await::RESOLVE);
			return yield Await::RACE;
		}, $ex);
	}

	public function testVoidRaceImmediateRejectLaterReject() : void{
		$ex = new DummyException();
		$ex2 = new DummyException();
		self::assertImmediateReject(function() use ($ex, $ex2) : Generator{
			yield Await::RESOLVE;
			self::voidCallbackImmediate($ex, yield Await::REJECT);
			yield Await::RESOLVE;
			self::voidCallbackLater($ex2, yield Await::REJECT);
			return yield Await::RACE;
		}, $ex);
	}

	public function testVoidRaceLaterResolveImmediateResolve() : void{
		$rand = [0x12345678, 0x4bcd3f96];
		self::assertImmediateResolve(function() use ($rand) : Generator{
			self::voidCallbackLater($rand[0], yield Await::RESOLVE);
			self::voidCallbackImmediate($rand[1], yield Await::RESOLVE);
			return yield Await::RACE;
		}, $rand[1]);
	}

	public function testVoidRaceLaterResolveImmediateReject() : void{
		$rand = 0x12345678;
		$ex = new DummyException();
		self::assertImmediateReject(function() use ($rand, $ex) : Generator{
			self::voidCallbackLater($rand, yield Await::RESOLVE);
			yield Await::RESOLVE;
			self::voidCallbackImmediate($ex, yield Await::REJECT);
			return yield Await::RACE;
		}, $ex);
	}

	public function testVoidRaceLaterResolveLaterResolve() : void{
		$rand = [0x12345678, 0x4BCD3F96];
		self::assertLaterResolve(function() use ($rand) : Generator{
			self::voidCallbackLater($rand[0], yield Await::RESOLVE);
			self::voidCallbackLater($rand[1], yield Await::RESOLVE);
			return yield Await::RACE;
		}, $rand[0]);
	}

	public function testVoidRaceLaterResolveLaterReject() : void{
		$rand = 0x12345678;
		$ex = new DummyException();
		self::assertLaterResolve(function() use ($rand, $ex) : Generator{
			self::voidCallbackLater($rand, yield Await::RESOLVE);
			self::voidCallbackLater($ex, yield Await::RESOLVE);
			return yield Await::RACE;
		}, $rand);
	}

	public function testVoidRaceLaterRejectImmediateResolve() : void{
		$ex = new DummyException();
		$rand = 0x1234567B;
		self::assertImmediateResolve(function() use ($ex, $rand) : Generator{
			yield Await::RESOLVE;
			self::voidCallbackLater($ex, yield Await::REJECT);
			self::voidCallbackImmediate($rand, yield Await::RESOLVE);
			return yield Await::RACE;
		}, $rand);
	}

	public function testVoidRaceLaterRejectImmediateReject() : void{
		$ex = new DummyException();
		$ex2 = new DummyException();
		self::assertImmediateReject(function() use ($ex, $ex2) : Generator{
			yield Await::RESOLVE;
			self::voidCallbackLater($ex, yield Await::REJECT);
			yield Await::RESOLVE;
			self::voidCallbackImmediate($ex2, yield Await::REJECT);
			return yield Await::RACE;
		}, $ex2);
	}

	public function testVoidRaceLaterRejectLaterResolve() : void{
		$ex = new DummyException();
		$rand = 0x1234567B;
		self::assertLaterReject(function() use ($ex, $rand) : Generator{
			yield Await::RESOLVE;
			self::voidCallbackLater($ex, yield Await::REJECT);
			self::voidCallbackLater($rand, yield Await::RESOLVE);
			return yield Await::RACE;
		}, $ex);
	}

	public function testVoidRaceLaterRejectLaterReject() : void{
		$ex = new DummyException();
		$ex2 = new DummyException();
		self::assertLaterReject(function() use ($ex, $ex2) : Generator{
			yield Await::RESOLVE;
			self::voidCallbackLater($ex, yield Await::REJECT);
			yield Await::RESOLVE;
			self::voidCallbackLater($ex2, yield Await::REJECT);
			return yield Await::RACE;
		}, $ex);
	}

	public function testVoidRaceImmediateResolveLaterResolveOnceLaterResolve() : void{
		$rand = [0x12345678, 0x4bcd3f96, 0xdeadbeef];
		self::assertLaterResolve(function() use ($rand) : Generator{
			self::voidCallbackImmediate($rand[0], yield Await::RESOLVE);
			self::voidCallbackLater($rand[1], yield Await::RESOLVE);
			$race = yield Await::RACE;
			self::voidCallbackLater($rand[2], yield Await::RESOLVE);
			self::$later = array_reverse(self::$later);
			$once = yield Await::ONCE;
			return [$race, $once];
		}, [$rand[0], $rand[2]]);
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

	public function testGeneratorAllResolve() : void{
		self::assertLaterResolve(function() : Generator{
			return yield Await::all([
				"a" => self::generatorReturnLater("b"),
				"c" => GeneratorUtil::empty("d"),
				"e" => self::generatorVoidLater(),
			]);
		}, [
			"a" => "b",
			"c" => "d",
			"e" => null,
		]);
	}

	public function testGeneratorAllEmpty() : void{
		try{
			Await::f2c(function() : Generator{
				yield Await::all([]);
			}, function() : void{
				self::assertTrue(false, "unexpected resolve call");
			});
		}catch(AwaitException $e){
			self::assertEquals("Unhandled async exception: Cannot await all on an empty array of generators", $e->getMessage());
			self::assertEquals("Cannot await all on an empty array of generators", $e->getPrevious()->getMessage());
		}
	}

	public function testGeneratorRaceResolve() : void{
		self::assertImmediateResolve(function() : Generator{
			return yield Await::race([
				"a" => self::generatorReturnLater("b"),
				"c" => GeneratorUtil::empty("d"),
				"e" => self::generatorVoidLater(),
			]);
		}, ["c", "d"]);
	}

	public function testGeneratorRaceEmpty() : void{
		try{
			Await::f2c(function() : Generator{
				yield Await::race([]);
			}, function() : void{
				self::assertTrue(false, "unexpected resolve call");
			});
		}catch(AwaitException $e){
			self::assertEquals("Unhandled async exception: Cannot race an empty array of generators", $e->getMessage());
			self::assertEquals("Cannot race an empty array of generators", $e->getPrevious()->getMessage());
		}
	}

	public function testSameImmediateResolveImmediateResolve() : void{
		$rand = [0x12345678, 0x4bcd3f96];
		self::assertImmediateResolve(function() use ($rand) : Generator{
			$cb = yield Await::RESOLVE;
			self::voidCallbackImmediate($rand[0], $cb);
			self::voidCallbackImmediate($rand[1], $cb);
			$once = yield Await::ONCE;
			return $once;
		}, $rand[0]);
	}

	public function testSameLaterResolveImmediateResolve() : void{
		$rand = [0x12345678, 0x4bcd3f96];
		self::assertImmediateResolve(function() use ($rand) : Generator{
			$cb = yield Await::RESOLVE;
			self::voidCallbackLater($rand[0], $cb);
			self::voidCallbackImmediate($rand[1], $cb);
			$once = yield Await::ONCE;
			return $once;
		}, $rand[1]);
	}

	public function testSameLaterResolveLaterResolve() : void{
		$rand = [0x12345678, 0x4bcd3f96];
		self::assertLaterResolve(function() use ($rand) : Generator{
			$cb = yield Await::RESOLVE;
			self::voidCallbackLater($rand[0], $cb);
			self::voidCallbackLater($rand[1], $cb);
			$once = yield Await::ONCE;
			return $once;
		}, $rand[0]);
	}

	public function testSameLaterRejectImmediateResolve() : void{
		$rand = 0x12345678;
		$ex = new DummyException();
		self::assertImmediateResolve(function() use ($rand, $ex) : Generator{
			$resolve = yield Await::RESOLVE;
			$reject = yield Await::REJECT;
			// they are the same pair!
			self::voidCallbackLater($ex, $reject);
			self::voidCallbackImmediate($rand, $resolve);
			$once = yield Await::ONCE;
			return $once;
		}, $rand);
		self::callLater();
	}


	protected function tearDown() : void{
		try{
			self::$later = [];
		}catch(Throwable $throwable){
			echo "Suppressed " . get_class($throwable) . ": " . $throwable->getMessage();
		}

		$assertions = self::getCount();
		self::assertGreaterThan(0, $assertions, "Test does not assert anything");
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
