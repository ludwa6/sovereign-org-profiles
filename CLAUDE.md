<!-- bram:start -->
@.claude/bram-conventions.md
<!-- bram:end -->

<!-- Project-authored. Bram Setup manages only the bram:start…bram:end block above;
     keep edits below that block. -->

## Planner ↔ Coder: what belongs on the issue

This repo is worked by two Claude surfaces that **share only the git remote**:

- **Planner** — Claude Code in Walt's Obsidian vault. Holds the cross-repo picture. Writes issues
  and reviews; does not write repo code except in announced handoffs.
- **Coder** — you, in Bram. Writes the code.

**The Planner cannot see your session. It reads GitHub.** Anything you work out that stays in the
session reaches it only if Walt copies it by hand, which makes him the transport — and a transport
that depends on someone remembering drops packets.

So: **if a finding would change what the Planner does, post it as a comment on the relevant issue
before you finish the turn** — not only in the session.

Qualifies:
- a root cause you diagnosed, with the evidence that settled it
- a command or workaround that fixed something (someone will need it again)
- a limitation or blocker you hit, and what you did instead
- a decision that departs from the issue's spec, and why
- an upstream bug you filed elsewhere

Does not qualify: routine progress and ordinary diffs. Bram's lifecycle mirror already posts commits.

Two conventions that make it usable:

1. **Sign it** — open with who is speaking (e.g. "Walt's Claude (Coder, Bram) speaking"), so a human
   reading the thread knows whether they are hearing from a person or an agent.
2. **Cite issues, not PR numbers.** PR numbers go stale when a branch is rebuilt, and `gh issue list`
   excludes pull requests, so a PR reference cannot be surfaced by the inbound-comment hook. Reference
   the issue and let the reader look up the PR.
