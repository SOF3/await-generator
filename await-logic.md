The following diagrams explain the control flow of await-generator.

## Await::ONCE, immediate resolution/rejection + return

```
|              Start                                                                                                                                                                                       Resolve
| Await          |                              |---|                                                        |-----------------|                                    |-----------|                            |
| |wakeup    ^    \ rewind       yield RESOLVE /     \ send callable                                        / queues internally \                    yield COLLECT / clear queue \ send result       return /
| |          |     \______       _____________/       \_____________                                       /                     \                   _____________/               \___________       ______/
| v     sleep|            \     /                                   \                                     /                       \                 /                                         \     /
| Generator                |---|                                     |---|                               /                         \           |---|                                           |---|
|                                                                         \        resolves immediately /                           \         /
|                                                                          \       ____________________/                             \       /
|                                                                           \     /                                                   \     /
| VoidCallback                                                               |---|                                                     |---|
|
```
