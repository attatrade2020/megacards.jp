# Development and review rules

- Use Codex as the primary implementation agent and Claude Code as an independent reviewer.
- Work on a feature branch and submit changes through a pull request; do not commit directly to the default branch unless explicitly requested.
- Before committing, run the relevant tests, linters, and syntax checks available in this repository.
- Never commit secrets, API keys, access tokens, cookies, production databases, private customer data, or generated browser profiles.
- Keep environment-specific values in ignored configuration files and document required variables in an example file.
- Preserve backward compatibility for deployed URLs, APIs, database schemas, browser-extension storage, and automation inputs unless a migration is included.
- In review mode, prioritize correctness, security, data loss, regressions, and missing tests over formatting preferences.
- Keep dependencies and setup instructions reproducible on another PC.

