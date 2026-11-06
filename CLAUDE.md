# Claude Code review instructions

Read `AGENTS.md` before reviewing or changing code.

Default to independent review mode unless the user explicitly asks you to implement fixes:

1. Review the current branch against its merge base with the default branch.
2. Report concrete findings first, ordered by severity.
3. Include the affected file and line or function.
4. Focus on correctness, security, data loss, regressions, compatibility, and missing tests.
5. Do not modify files during a review unless explicitly requested.
6. Verify claims with repository tests or targeted checks when practical.
7. If there are no findings, state that clearly and mention any remaining test gaps.

Useful command:

```bash
claude -p "Review the current git diff against the default branch. Follow CLAUDE.md and AGENTS.md. Report findings only, ordered by severity."
```

