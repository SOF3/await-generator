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
use stdClass;

/**
 * @small
 */
class ChannelTest extends TestCase{
	public function testSendFirst() : void{
		/** @var Channel<string> $channel */
		$channel = new Channel;
		$clock = new MockClock;

		$eventCounter = 0;

		Await::f2c(function() use($channel, $clock, &$eventCounter){
			yield from $clock->sleepUntil(1);
			yield from $channel->sendAndWait("a");
			self::assertSame(3, $clock->currentTick());
			$eventCounter += 1;
		});

		Await::f2c(function() use($channel, $clock, &$eventCounter){
			yield from $clock->sleepUntil(2);
			yield from $channel->sendAndWait("b");
			self::assertSame(5, $clock->currentTick());
			$eventCounter += 1;
		});

		Await::f2c(function() use($channel, $clock, &$eventCounter){
			yield from $clock->sleepUntil(3);
			$receive = yield from $channel->receive();
			self::assertSame(3, $clock->currentTick());
			self::assertSame("a", $receive);
			$eventCounter += 1;
		});

		Await::f2c(function() use($channel, $clock, &$eventCounter){
			yield from $clock->sleepUntil(4);
			yield from $channel->sendAndWait("c");
			self::assertSame(6, $clock->currentTick());
			$eventCounter += 1;
		});

		Await::f2c(function() use($channel, $clock, &$eventCounter){
			yield from $clock->sleepUntil(5);
			$receive = yield from $channel->receive();
			self::assertSame(5, $clock->currentTick());
			self::assertSame("b", $receive);
			$eventCounter += 1;
		});

		Await::f2c(function() use($channel, $clock, &$eventCounter){
			yield from $clock->sleepUntil(6);
			$receive = yield from $channel->receive();
			self::assertSame(6, $clock->currentTick());
			self::assertSame("c", $receive);
			$eventCounter += 1;
		});

		Await::f2c(function() use($channel, $clock, &$eventCounter){
			yield from $clock->sleepUntil(7);
			$receive = yield from $channel->receive();
			self::assertSame(8, $clock->currentTick());
			self::assertSame("d", $receive);
			$eventCounter += 1;
		});

		Await::f2c(function() use($channel, $clock, &$eventCounter){
			yield from $clock->sleepUntil(8);
			yield from $channel->sendAndWait("d");
			self::assertSame(8, $clock->currentTick());
			$eventCounter += 1;
		});

		$clock->nextTick(1);
		self::assertSame(0, $eventCounter);
		self::assertSame(1, $channel->getSendQueueSize());
		self::assertSame(0, $channel->getReceiveQueueSize());

		$clock->nextTick(2);
		self::assertSame(0, $eventCounter);
		self::assertSame(2, $channel->getSendQueueSize());
		self::assertSame(0, $channel->getReceiveQueueSize());

		$clock->nextTick(3);
		self::assertSame(2, $eventCounter);
		self::assertSame(1, $channel->getSendQueueSize());
		self::assertSame(0, $channel->getReceiveQueueSize());

		$clock->nextTick(4);
		self::assertSame(2, $eventCounter);
		self::assertSame(2, $channel->getSendQueueSize());
		self::assertSame(0, $channel->getReceiveQueueSize());

		$clock->nextTick(5);
		self::assertSame(4, $eventCounter);
		self::assertSame(1, $channel->getSendQueueSize());
		self::assertSame(0, $channel->getReceiveQueueSize());

		$clock->nextTick(6);
		self::assertSame(6, $eventCounter);
		self::assertSame(0, $channel->getSendQueueSize());
		self::assertSame(0, $channel->getReceiveQueueSize());

		$clock->nextTick(7);
		self::assertSame(6, $eventCounter);
		self::assertSame(0, $channel->getSendQueueSize());
		self::assertSame(1, $channel->getReceiveQueueSize());

		$clock->nextTick(8);
		self::assertSame(8, $eventCounter);
		self::assertSame(0, $channel->getSendQueueSize());
		self::assertSame(0, $channel->getReceiveQueueSize());
	}

	public function testReceiveFirst() : void{
		/** @var Channel<string> $channel */
		$channel = new Channel;
		$clock = new MockClock;

		$eventCounter = 0;

		Await::f2c(function() use($channel, $clock, &$eventCounter){
			yield from $clock->sleepUntil(1);
			$receive = yield from $channel->receive();
			self::assertSame(3, $clock->currentTick());
			self::assertSame("a", $receive);
			$eventCounter += 1;
		});

		Await::f2c(function() use($channel, $clock, &$eventCounter){
			yield from $clock->sleepUntil(2);
			$receive = yield from $channel->receive();
			self::assertSame(5, $clock->currentTick());
			self::assertSame("b", $receive);
			$eventCounter += 1;
		});

		Await::f2c(function() use($channel, $clock, &$eventCounter){
			yield from $clock->sleepUntil(3);
			yield from $channel->sendAndWait("a");
			self::assertSame(3, $clock->currentTick());
			$eventCounter += 1;
		});

		Await::f2c(function() use($channel, $clock, &$eventCounter){
			yield from $clock->sleepUntil(4);
			$receive = yield from $channel->receive();
			self::assertSame(6, $clock->currentTick());
			self::assertSame("c", $receive);
			$eventCounter += 1;
		});

		Await::f2c(function() use($channel, $clock, &$eventCounter){
			yield from $clock->sleepUntil(5);
			yield from $channel->sendAndWait("b");
			self::assertSame(5, $clock->currentTick());
			$eventCounter += 1;
		});

		Await::f2c(function() use($channel, $clock, &$eventCounter){
			yield from $clock->sleepUntil(6);
			yield from $channel->sendAndWait("c");
			self::assertSame(6, $clock->currentTick());
			$eventCounter += 1;
		});

		Await::f2c(function() use($channel, $clock, &$eventCounter){
			yield from $clock->sleepUntil(7);
			yield from $channel->sendAndWait("d");
			self::assertSame(8, $clock->currentTick());
			$eventCounter += 1;
		});

		Await::f2c(function() use($channel, $clock, &$eventCounter){
			yield from $clock->sleepUntil(8);
			$receive = yield from $channel->receive();
			self::assertSame(8, $clock->currentTick());
			self::assertSame("d", $receive);
			$eventCounter += 1;
		});

		$clock->nextTick(1);
		self::assertSame(0, $eventCounter);
		self::assertSame(0, $channel->getSendQueueSize());
		self::assertSame(1, $channel->getReceiveQueueSize());

		$clock->nextTick(2);
		self::assertSame(0, $eventCounter);
		self::assertSame(0, $channel->getSendQueueSize());
		self::assertSame(2, $channel->getReceiveQueueSize());

		$clock->nextTick(3);
		self::assertSame(2, $eventCounter);
		self::assertSame(0, $channel->getSendQueueSize());
		self::assertSame(1, $channel->getReceiveQueueSize());

		$clock->nextTick(4);
		self::assertSame(2, $eventCounter);
		self::assertSame(0, $channel->getSendQueueSize());
		self::assertSame(2, $channel->getReceiveQueueSize());

		$clock->nextTick(5);
		self::assertSame(4, $eventCounter);
		self::assertSame(0, $channel->getSendQueueSize());
		self::assertSame(1, $channel->getReceiveQueueSize());

		$clock->nextTick(6);
		self::assertSame(6, $eventCounter);
		self::assertSame(0, $channel->getSendQueueSize());
		self::assertSame(0, $channel->getReceiveQueueSize());

		$clock->nextTick(7);
		self::assertSame(6, $eventCounter);
		self::assertSame(1, $channel->getSendQueueSize());
		self::assertSame(0, $channel->getReceiveQueueSize());

		$clock->nextTick(8);
		self::assertSame(8, $eventCounter);
		self::assertSame(0, $channel->getSendQueueSize());
		self::assertSame(0, $channel->getReceiveQueueSize());
	}

	public function testNonBlockSend() : void{
		/** @var Channel<string> $channel */
		$channel = new Channel;
		$clock = new MockClock;

		$eventCounter = 0;

		Await::f2c(function() use($channel, $clock, &$eventCounter){
			yield from $clock->sleepUntil(1);
			$channel->sendWithoutWait("a");
			$eventCounter += 1;
		});

		Await::f2c(function() use($channel, $clock, &$eventCounter){
			yield from $clock->sleepUntil(2);
			$receive = yield from $channel->receive();
			self::assertSame(2, $clock->currentTick());
			self::assertSame("a", $receive);
			$eventCounter += 1;
		});


		Await::f2c(function() use($channel, $clock, &$eventCounter){
			yield from $clock->sleepUntil(3);
			$receive = yield from $channel->receive();
			self::assertSame(4, $clock->currentTick());
			self::assertSame("a", $receive);
			$eventCounter += 1;
		});

		Await::f2c(function() use($channel, $clock, &$eventCounter){
			yield from $clock->sleepUntil(4);
			$channel->sendWithoutWait("a");
			$eventCounter += 1;
		});

		$clock->nextTick(1);
		self::assertSame(1, $eventCounter);
		self::assertSame(1, $channel->getSendQueueSize());
		self::assertSame(0, $channel->getReceiveQueueSize());

		$clock->nextTick(2);
		self::assertSame(2, $eventCounter);
		self::assertSame(0, $channel->getSendQueueSize());
		self::assertSame(0, $channel->getReceiveQueueSize());

		$clock->nextTick(3);
		self::assertSame(2, $eventCounter);
		self::assertSame(0, $channel->getSendQueueSize());
		self::assertSame(1, $channel->getReceiveQueueSize());

		$clock->nextTick(4);
		self::assertSame(4, $eventCounter);
		self::assertSame(0, $channel->getSendQueueSize());
		self::assertSame(0, $channel->getReceiveQueueSize());
	}

	public function testTrySend() : void{
		/** @var Channel<string> $channel */
		$channel = new Channel;

		$received = false;

		self::assertFalse($channel->trySend("a"));

		Await::f2c(function() use($channel, &$received) {
			$value = yield from $channel->receive();
			self::assertSame("b", $value);
			$received = true;
		});

		self::assertTrue($channel->trySend("b"));
	}

	public function testTryReceive() : void{
		/** @var Channel<string> $channel */
		$channel = new Channel;

		$receive = $channel->tryReceiveOr("b");
		self::assertSame("b", $receive);

		$channel->sendWithoutWait("a");
		$receive = $channel->tryReceiveOr("b");

		self::assertSame("a", $receive);
	}

	public function testTryCancelSender() : void{
		$ok = false;
		Await::f2c(function() use(&$ok){
			/** @var Channel<null> $channel */
			$channel = new Channel;

			[$which, $_] = yield from Await::safeRace([
				$channel->sendAndWait(null),
				GeneratorUtil::empty(null),
			]);
			self::assertSame(1, $which);

			$ret = $channel->tryReceiveOr("no sender");
			self::assertSame("no sender", $ret);
			$ok = true;
		});

		self::assertTrue($ok, "test run complete");
	}

	public function testTryCancelReceiver() : void{
		$ok = false;
		Await::f2c(function() use(&$ok){
			/** @var Channel<null> $channel */
			$channel = new Channel;

			[$which, $_] = yield from Await::safeRace([
				$channel->receive(),
				GeneratorUtil::empty(null),
			]);
			self::assertSame(1, $which);

			$channel->sendWithoutWait(null);
			$ok = true;
		});

		self::assertTrue($ok, "test run complete");
	}
}
