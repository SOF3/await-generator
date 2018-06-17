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

class CallbackHellControl{
	public function execute() : void{
		$this->async_a(function(...$a){
			$this->async_b(function($b) use ($a){
				[$a1, $a2] = $a;
				echo "$a1, $a2, $b\n";
			});
		});
	}

	private function async_a(callable $complete) : void{
		$this->async_a_1(function($a1) use ($complete){
			$this->async_a_2(function($a2) use ($complete, $a1){
				$complete($a1, $a2);
			});
		});
	}

	private function async_a_1(callable $complete) : void{
		$this->dummy("a_1", $complete); // dummy
	}

	private function async_a_2(callable $complete) : void{
		$this->dummy("a_2", $complete); // dummy
	}

	private function async_b(callable $complete) : void{
		$this->dummy("b", $complete); // dummy
	}

	private function dummy($return, callable $onComplete) : void{
		$onComplete($return);
	}
}
