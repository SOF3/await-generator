Changelog
===

## 3.6.1
- Fixed syntax error

## 3.6.0
- Added `Traverser::asGenerator()`

## 3.5.2
- Fixed `Channel` crashing if there is only one pending sender/receiver and it gets canceled.

## 3.5.1
- Added `PubSub` class

## 3.5.0
- Added `Await::safeRace`

## 3.5.0
- Added `Await::safeRace`

## 3.4.4
- Support virion 3.0 spec

## 3.4.3
- Fixed Traverser not passing resolved value to the inner generator (#184)

## 3.4.2
- Updated phpstan hint to allow promise resolver to have no arguments
- Deprecated `yield $generator`, use `yield from $generator` instead

## 3.4.1
- Added `Loading::getSync`

## 3.4.0
- Added `Channel`

## 3.3.0
- Added `Await::promise`
- Deprecated all constnat yields in favour of `Await::promise`

## 3.2.0
- Added `Mutex`

## 3.1.1
- Allow `Await::all([])` to simply return empty array

## 3.1.0
- Added `Traverser` API
	- Added `Traverser::next()`
	- Added `Traverser::collect()`
	- Added `Traverser::interrupt()`

## 3.0.0
- Added `Await::all()` and `Await::race()` for a generator interface
- Fixed crash during double promise resolution, ignores second call instead
- Marked some internal functions @internal or private

## 2.3.0
- Debug backtrace includes objects.
- Added `Await::RESOLVE_MULTI`

## 2.2.0
- Added debug mode
	- Generator traces are appended to throwable traces under debug mode
- Resolve function (result of `yield Await::RESOLVE`) no longer requires a parameter

## 2.1.0
- Added `Await::RACE`
- Fixed later-resolve/immediate-reject with `Await::ALL`

## 2.0.0
Complete rewrite

## 1.0.0
- Entry level
	- `Await::func`
	- `Await::closure`
- Intermediate level
	- `Await::FROM` (currently equivalent to `yield from`)
- Implementation level
	- `Await::ASYNC`
	- `Await::CALLBACK`
