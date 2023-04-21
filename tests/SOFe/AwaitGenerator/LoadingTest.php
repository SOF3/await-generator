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

use PHPUnit\Framework\TestCase;

/**
 * @small
 */
class LoadingTest extends TestCase{
	public function testImmediate() : void{
		$loading = new Loading(fn() => GeneratorUtil::empty("a"));

		self::assertSame("a", $loading->getSync(1));

		$done = false;
		Await::f2c(function() use($loading, &$done) {
			$value = yield from $loading->get();
			self::assertSame("a", $value);
			self::assertSame("a", $loading->getSync(1));

			$value = yield from $loading->get();
			self::assertSame("a", $value, "Cannot get value the second time");
			self::assertSame("a", $loading->getSync(1));

			$done = true;
		});

		self::assertTrue($done, "Cannot get value twice");
	}

	public function testDeferred() : void{
		$clock = new MockClock;

		$loading = new Loading(function() use($clock) {
			yield from $clock->sleepUntil(1);
			return "b";
		});

		self::assertSame(1, $loading->getSync(1));

		$beforeDone = false;
		Await::f2c(function() use($loading, &$beforeDone) {
			$value = yield from $loading->get();
			self::assertSame("b", $value);
			self::assertSame("b", $loading->getSync(1));

			$value = yield from $loading->get();
			self::assertSame("b", $value, "Cannot get value the second time");
			self::assertSame("b", $loading->getSync(1));

			$beforeDone = true;
		});

		$afterDone = false;
		Await::f2c(function() use($loading, $clock, &$afterDone) {
			yield from $clock->sleepUntil(2);

			$value = yield from $loading->get();
			self::assertSame("b", $value);
			self::assertSame("b", $loading->getSync(1));

			$value = yield from $loading->get();
			self::assertSame("b", $value, "Cannot get value the second time");
			self::assertSame("b", $loading->getSync(1));

			$afterDone = true;
		});

		self::assertFalse($beforeDone);
		self::assertFalse($afterDone);

		self::assertSame(1, $loading->getSync(1));

		$clock->nextTick(1);

		self::assertTrue($beforeDone);
		self::assertFalse($afterDone);

		$clock->nextTick(2);

		self::assertTrue($beforeDone);
		self::assertTrue($afterDone);
	}

	public function testSyncWinSyncCancel() : void{
		$fast = new Loading(fn() => GeneratorUtil::empty("instant"));
		$slow = new Loading(fn() => GeneratorUtil::empty("later"));

		$done = false;
		$hasSlowReturn = false;

		Await::f2c(function() use($fast, $slow, &$done, &$hasSlowReturn) {
			[$which, $_] = yield from Await::safeRace([
				"fast" => $fast->get(),
				"slow" => (function() use($slow, &$hasSlowReturn) {
					yield from $slow->get();
					$hasSlowReturn = true;
				})(),
			]);
			self::assertEquals("fast", $which);

			$done = true;
		});

		self::assertTrue($done, "execution complete");
		self::assertFalse($hasSlowReturn, "loser should not return after cancel");
	}

	public function testAsyncWinAsyncCancel() : void{
		$clock = new MockClock;

		$fast = new Loading(function() use($clock) {
			yield from $clock->sleepUntil(2);
			return "earlier";
		});
		$slow = new Loading(function() use($clock) {
			yield from $clock->sleepUntil(2);
			return "later";
		});

		$done = false;
		$hasSlowReturn = false;

		Await::f2c(function() use($fast, $slow, $clock, &$done, &$hasSlowReturn) {
			[$which, $_] = yield from Await::safeRace([
				"fast" => $fast->get(),
				"slow" => (function() use($slow, &$hasSlowReturn) {
					yield from $slow->get();
					$hasSlowReturn = true;
				})(),
			]);
			self::assertEquals(2, $clock->currentTick());
			self::assertEquals("fast", $which);

			$done = true;
		});

		$clock->nextTick(1);
		self::assertFalse($done, "pending execution");

		$clock->nextTick(2);
		self::assertTrue($done, "execution complete");
		self::assertFalse($hasSlowReturn, "loser should not return after cancel");
	}

	public function testCallback() : void{
		[$loading, $resolve] = Loading::byCallback();
		self::assertEquals(12345, $loading->getSync(12345));
		$resolve(98765);
		self::assertEquals(98765, $loading->getSync(12345));
	}
}
