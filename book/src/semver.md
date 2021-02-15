# Versioning concerns
await-generator is guaranteed to be
shade-compatible, backward-compatible and partly forward-compatible.

Await-generator uses generator objects for communication.
The values passed through generators (such as `Await::ONCE`)
are constant strings that are guaranteed to remain unchanged within a major version.
Therefore, multiple shaded versions of await-generator can be used together.

New constants may be added over minor versions.
Older versions will crash when they receive constants from newer versions.

Only `Await::f2c`/`Await::g2c` loads await-generator code.
Functions that merely `yield` values from the `Await` class
will not affect the execution logic.
Therefore, the version of await-generator
on which `Await::f2c`/`Await::g2c` is called
determines the highest version to use.

(For those who do not use virion framework and are confused:
await-generator is versioned just like the normal semver for you.)
