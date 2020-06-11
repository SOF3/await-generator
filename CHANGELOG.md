Changelog
===

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
