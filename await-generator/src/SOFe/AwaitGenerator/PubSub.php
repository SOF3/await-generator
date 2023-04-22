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

use RuntimeException;

/**
 * A pubsub allows coroutines to publish a message that is received by all subscribres.
 * @template T
 */
final class PubSub{
	/** @var Channel<T>[] */
	private array $subscribers = [];

	/**
	 * If a subscriber is lagging for more than $maxLag items behind,
	 * a RuntimeException is thrown to help detect memory leaks in advance.
	 * Set it to `null` to disable the check.
	 */
	public function __construct(
		private ?int $maxLag = 10000,
	) {}

	/**
	 * Publishes a message and return.
	 *
	 * This method does not wait for the event to be actually received by subscribers.
	 *
	 * @phpstan-param T $item
	 */
	public function publish($item) : void{
		foreach($this->subscribers as $subscriber) {
			$subscriber->sendWithoutWait($item);
			if($this->maxLag !== null && $subscriber->getSendQueueSize() > $this->maxLag) {
				throw new RuntimeException("A subscriber has been lagging for $this->maxLag items. Forgot to call \$traverser->interrupt()?");
			}
		}
	}

	/**
	 * Subscribes to the messages of this topic.
	 *
	 * The returned traverser does not return any messages published prior to calling `subscribe()`.
	 *
	 * Subscribers are tracked in the `PubSub`.
	 * To avoid memory leak,
	 * callers to this method must interrupt the traverser in a `finally` block:
	 * WARNING: Otherwise, a RuntimeException will be thrown once `maxLag` is
	 * reached!
	 *
	 * ```
	 * $sub = $pubsub->subscribe();
	 * try {
	 *	   while($sub->next($item)) {
	 *	       // do something with $item
	 *	   }
	 * } finally {
	 *     yield from $sub->interrupt();
	 * }
	 * ```
	 *
	 * @return Traverser<T>
	 */
	public function subscribe() : Traverser{
		$channel = new Channel;

		return Traverser::fromClosure(function() use($channel){
			try{
				$this->subscribers[spl_object_id($channel)] = $channel;

				while(true) {
					$item = yield from $channel->receive();
					yield $item => Traverser::VALUE;
				}
			}finally{
				unset($this->subscribers[spl_object_id($channel)]);
			}
		});
	}

	public function isEmpty() : bool {
		return count($this->subscribers) === 0;
	}

	public function getSubscriberCount() : int {
		return count($this->subscribers);
	}
}
