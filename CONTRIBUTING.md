# Contributing guidelines

## Scope

While this project is primarily used in the PocketMine ecosystem, it is also intended to be compatible with the general PHP ecosystem.
All core functionality must be compatible with non-PocketMine environments.

## AI policy

In principle, vibe coding contributions are not allowed.

What is allowed:
- **AI in tooling**: Use AI for non-file-editing tasks, including review, debugging and ideation
- **Short completions**: AI-assisted editor completions, up to no more than one line of code at a time
- **Automated review**: Automated review through CI tools (such as GitHub Copilot Review) is allowed,
  but it must be explicitly indicated that they are AI review and clearly separated from human review,
  e.g. through a dedicated username.

What is explicitly not allowed:
- **Comments**: Write comments directly. Use AI for single-clause translations but not for full paragraph writing.
  LLM-generated prose, especially when more than one line, tends to be unreadable to many English readers.
- **Tests**: await-generator has dozens of carefully handpicked unit test cases.
  Do not pollute the tests with AI slop.
- **Issues/comments**: Issue/PR body and comments must be written by a human.
  This is not a place for agents to talk to each other.

Every line of code must either be completely drafted by a human or otherwise thoroughly reviewed
to the extent that the purpose of every token is well understood.
