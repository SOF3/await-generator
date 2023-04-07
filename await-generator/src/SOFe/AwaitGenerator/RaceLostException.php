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

use Exception;

/**
 * The exception to throw into loser generators of
 * a {@link Await::safeRace()}.
 * 
 * If your generator has side effects, please consider
 * handling this exception by taking cancellation in a
 * `finally` block. Otherwise, if you prefer the `catch`
 * block, please re-throw this exception at the end.
 * (Please refer to {@link AwaitTest::testSafeRaceCancel()}.)
 * 
 * NOTICE: it would not cause a crash even though your
 * generator did not catch it.
 */
final class RaceLostException extends Exception{
	public function __construct() {
	}
}
