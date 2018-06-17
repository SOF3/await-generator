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

namespace virion_tests\SOFe\AwaitGenerator;

use Generator;
use function printStackTrace;
use SOFe\AwaitGenerator\Await;
use function array_shift;

class AwaitTest{
	/** @var callable[] */
	private $later = [];

	public function __construct(){
		echo spl_object_id($this) . ": ", "Constructed " . __CLASS__ . "\n";
	}

	public function execute() : void{
		Await::closure(function() : Generator{
			echo spl_object_id($this) . ": ", "Before a\n";
			$a = yield Await::FROM => $this->async_a();
			echo spl_object_id($this) . ": ", "Before b\n";
			$b = yield Await::FROM => $this->async_b();
			return [$a, $b];
		}, function($a, $b){
			[$a1, $a2] = $a;
			echo "$a1, $a2, $b\n";
		});

		while(!empty($this->later)){
			array_shift($this->later)();
		}
	}

	private function async_a() : Generator{
		echo spl_object_id($this) . ": ", "Before a1\n";
		$a1 = yield Await::FROM => $this->async_a_1();
		echo spl_object_id($this) . ": ", "Before a2\n";
		$a2 = yield Await::FROM => $this->async_a_2();
		echo spl_object_id($this) . ": ", "After a2\n";
		return [$a1, $a2];
	}

	private function async_a_1() : Generator{
		echo spl_object_id($this) . ": ", "Called async_a_1\n";
		$this->dummy("a_1", yield Await::CALLBACK);
		return yield Await::ASYNC;
	}

	private function async_a_2() : Generator{
		echo spl_object_id($this) . ": ", "Called async_a_2\n";
		return yield Await::ASYNC => $this->dummy("a_2", yield Await::CALLBACK);
	}

	private function async_b() : Generator{
		echo spl_object_id($this) . ": ", "Called async_b\n";
		return yield Await::ASYNC => $this->dummy("b", yield Await::CALLBACK);
	}

	private function dummy($return, callable $onComplete) : void{
		$onComplete($return);
	}
}
