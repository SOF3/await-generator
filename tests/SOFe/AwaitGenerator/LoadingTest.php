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

		$done = false;
		Await::f2c(function() use($loading, &$done) {
			$value = yield from $loading->get();
			self::assertSame("a", $value);

			$value = yield from $loading->get();
			self::assertSame("a", $value, "Cannot get value the second time");

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

		$beforeDone = false;
		Await::f2c(function() use($loading, &$beforeDone) {
			$value = yield from $loading->get();
			self::assertSame("b", $value);

			$value = yield from $loading->get();
			self::assertSame("b", $value, "Cannot get value the second time");

			$beforeDone = true;
		});

		$afterDone = false;
		Await::f2c(function() use($loading, $clock, &$afterDone) {
			yield from $clock->sleepUntil(2);

			$value = yield from $loading->get();
			self::assertSame("b", $value);

			$value = yield from $loading->get();
			self::assertSame("b", $value, "Cannot get value the second time");

			$afterDone = true;
		});

		self::assertFalse($beforeDone);
		self::assertFalse($afterDone);

		$clock->nextTick(1);

		self::assertTrue($beforeDone);
		self::assertFalse($afterDone);

		$clock->nextTick(2);

		self::assertTrue($beforeDone);
		self::assertTrue($afterDone);
	}
}
