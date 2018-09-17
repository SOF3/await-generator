Changelog
===

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
