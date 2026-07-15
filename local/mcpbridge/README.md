# MCP Bridge (local_mcpbridge)

A Moodle local plugin that adds web service (external) functions for **creating
course activities** — the piece Moodle core's Web Services API is missing. It lets
an external client (such as an AI assistant via the Model Context Protocol) build
content in a course over REST.

## What it adds

The plugin registers a single web service, **MCP Bridge Service**, exposing:

| Function | Creates |
|---|---|
| `local_mcpbridge_create_page` | A Page activity (HTML content) |
| `local_mcpbridge_create_book` | A Book activity + its first chapter |
| `local_mcpbridge_create_label` | A Label (inline text/HTML) |
| `local_mcpbridge_create_url` | A URL resource |
| `local_mcpbridge_create_quiz` | An empty Quiz activity (settings only) |
| `local_mcpbridge_add_quiz_question` | A multiple-choice question added to a quiz |

Every function uses Moodle's official `add_moduleinfo()` helper (the same code path
the web UI uses), so activities are created correctly and the plugin stays
upgrade-safe. Each call validates parameters, checks the context, and requires the
appropriate capability (`moodle/course:manageactivities`, and additionally
`mod/quiz:manage` + `moodle/question:add` for quiz questions).

## Requirements

- Moodle 4.2 or later (uses the namespaced `core_external` API).

## Installation

1. Copy this folder to `{yourmoodle}/local/mcpbridge/`.
2. Log in as an admin and visit **Site administration** — Moodle detects the plugin
   and prompts you to upgrade the database. Confirm.
3. Enable **Web services** and the **REST** protocol, add the functions to a service
   (the plugin ships **MCP Bridge Service**), authorise a user, and create a token.

Or install from a ZIP via **Site administration → Plugins → Install plugins**.

## License

2026 Namaa Alhawary. Licensed under the **GNU GPL v3 or later** —
<https://www.gnu.org/licenses/gpl-3.0.html>.
