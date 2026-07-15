# Contributing to MCP-Moodle

Thanks for your interest in contributing! This project has two parts — a Moodle
plugin (`local/mcpbridge/`, PHP) and an MCP server (`moodle-mcp/`, Python) — and
contributions to either are welcome.

## Ways to contribute

- **Report a bug** — open an [issue](../../issues) with steps to reproduce, your
  Moodle version, and the raw JSON response if a web service call failed.
- **Request a feature** — e.g. a new activity type (Assignment, Forum, Choice…)
  or a new MCP tool.
- **Send a pull request** — fix a bug, add a function, or improve the docs.

## Fork & pull-request workflow

1. **Fork** this repo (top-right **Fork** button on GitHub).
2. **Clone** your fork and create a branch:
   ```bash
   git clone git@github.com:YOUR_USERNAME/MCP-Moodle.git
   cd MCP-Moodle
   git checkout -b feature/create-forum
   ```
3. **Make your change** (see the patterns below).
4. **Test it** — lint and, ideally, run it against a real Moodle test site.
5. **Commit & push** to your fork:
   ```bash
   git commit -m "Add create_forum external function"
   git push origin feature/create-forum
   ```
6. **Open a Pull Request** from your branch to `NmaaAlhawary/MCP-Moodle:main`.

## Adding a new plugin function (the pattern)

Every activity function follows the same three-method shape. Copy an existing
file in `local/mcpbridge/classes/external/` (e.g. `create_page.php`) and adapt it:

1. Create `classes/external/create_<thing>.php` with `execute_parameters()`,
   `execute()`, and `execute_returns()`.
2. In `execute()`: `validate_parameters()` → `validate_context()` →
   `require_capability(...)` → use Moodle's `add_moduleinfo()` (never write to
   module tables directly).
3. Register it in `db/services.php`.
4. Bump `$plugin->version` in `version.php`.
5. (Optional) Add a matching tool in `moodle-mcp/server.py`.

## Coding standards

- **PHP** — follow the [Moodle coding style](https://moodledev.io/general/development/policies/codingstyle).
  Lint before committing: `php -l path/to/file.php`.
- **Python** — type-hint inputs and give every MCP tool a clear docstring. Write
  tools must be prefixed `⚠️ WRITES LIVE DATA` in their docstring.
- Keep the read/write split intact: never expose a write tool outside the
  `MOODLE_ALLOW_WRITE` gate.

## Before you open a PR

- [ ] `php -l` passes on any changed PHP files.
- [ ] `python -m py_compile moodle-mcp/server.py` passes.
- [ ] No secrets committed (`.env` is gitignored — keep it that way).
- [ ] README updated if you added a tool or function.

## Reporting security issues

Please **do not** open a public issue for security problems (e.g. a way to bypass
the capability checks). Instead, contact the maintainer directly.

By contributing, you agree that your contributions are licensed under the
project's [MIT License](LICENSE).
