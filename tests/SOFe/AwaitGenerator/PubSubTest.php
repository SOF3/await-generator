<?php

/*
 * await-generator
 *
 * Copyright (C) 2023 SOFe
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
class PubSubTest extends TestCase{
	public function testSubFirst() : void{
		/** @var PubSub<int> $pubsub */
		$pubsub = new PubSub;

		$run = 0;
		for($i = 0; $i < 3; $i++) {
			Await::f2c(function() use($pubsub, &$run){
				$sub = $pubsub->subscribe();

				self::assertTrue(yield from $sub->next($item), "subscriber gets first item");
				self::assertEquals(1, $item);

				self::assertTrue(yield from $sub->next($item), "subscriber gets second item");
				self::assertEquals(2, $item);

				yield from $sub->interrupt();

				$run += 1;
			});
		}

		$pubsub->publish(1);
		$pubsub->publish(2);

		self::assertEquals(3, $run);

		self::assertEquals(0, $pubsub->getSubscriberCount());
		self::assertTrue($pubsub->isEmpty());
	}

	public function testPubFirst() : void{
		/** @var PubSub<int> $pubsub */
		$pubsub = new PubSub;

		$pubsub->publish(1);
		$pubsub->publish(2);

		$run = 0;
		for($i = 0; $i < 3; $i++) {
			Await::f2c(function() use($pubsub, &$run){
				$sub = $pubsub->subscribe();
				$run++;
				yield from $sub->next($_);
				self::fail("subscriber should not receive items published before subscribe() call");
			});
		}
		self::assertEquals(3, $run);
	}
}
