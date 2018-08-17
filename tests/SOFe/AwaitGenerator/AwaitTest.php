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
use function var_dump;

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

	/**
	 * @depends testEmptyGeneratorCreation
	 */
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
		$rand = 0xCAFEFEED;
		self::assertImmediateResolve(function() use ($rand) : Generator{
			return yield self::voidCallbackImmediate($rand, yield) => Await::ONCE;
		}, $rand);
	}

	public function testOneVoidImmediateResolve() : void{
		$rand = 0xDEADBEEF;
		self::assertImmediateResolve(function() use ($rand) : Generator{
			return yield self::voidCallbackImmediate($rand, yield Await::RESOLVE) => Await::ONCE;
		}, $rand);
	}

	public function testOneVoidLaterResolve() : void{
		$rand = 0xFEEDFACE;
		self::assertLaterResolve(function() use ($rand) : Generator{
			return yield self::voidCallbackLater($rand, yield Await::RESOLVE) => Await::ONCE;
		}, $rand);
	}

	public function testOneVoidImmediateReject() : void{
		$exception = new DummyException();
		self::assertImmediateReject(function() use ($exception) : Generator{
			yield Await::RESOLVE; // unused
			yield self::voidCallbackImmediate($exception, yield Await::REJECT) => Await::ONCE;
		}, $exception);
	}

	public function testOneVoidLaterReject() : void{
		$exception = new DummyException();
		self::assertLaterReject(function() use ($exception) : Generator{
			yield Await::RESOLVE; // unused
			yield self::voidCallbackLater($exception, yield Await::REJECT) => Await::ONCE;
		}, $exception);
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
