<div align="center">

<img src="https://raw.githubusercontent.com/NmaaAlhawary/MCP-Moodle/main/assets/hero.svg" alt="MCP-Moodle" width="100%">

<br><br>

[![Website](https://img.shields.io/badge/website-live-3fb950.svg?style=flat-square&logo=githubpages&logoColor=white)](https://nmaaalhawary.github.io/MCP-Moodle/)
[![PyPI](https://img.shields.io/pypi/v/moodle-mcp-bridge?style=flat-square&logo=pypi&logoColor=white&color=3775A9)](https://pypi.org/project/moodle-mcp-bridge/)
[![CI](https://img.shields.io/github/actions/workflow/status/NmaaAlhawary/MCP-Moodle/ci.yml?branch=main&style=flat-square&label=CI&logo=github)](https://github.com/NmaaAlhawary/MCP-Moodle/actions/workflows/ci.yml)
[![License](https://img.shields.io/badge/license-MIT-yellow.svg?style=flat-square)](https://github.com/NmaaAlhawary/MCP-Moodle/blob/main/LICENSE)
[![Python](https://img.shields.io/badge/python-3.11%2B-3776AB.svg?style=flat-square&logo=python&logoColor=white)](https://www.python.org/)
[![Moodle](https://img.shields.io/badge/moodle-4.2%2B-f98012.svg?style=flat-square&logo=moodle&logoColor=white)](https://moodle.org/)
[![MCP](https://img.shields.io/badge/MCP-compatible-8A2BE2.svg?style=flat-square)](https://modelcontextprotocol.io/)
[![Release](https://img.shields.io/github/v/release/NmaaAlhawary/MCP-Moodle?style=flat-square&color=3fb950)](https://github.com/NmaaAlhawary/MCP-Moodle/releases)
[![PRs welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg?style=flat-square)](https://github.com/NmaaAlhawary/MCP-Moodle/blob/main/CONTRIBUTING.md)

<samp>🌐 **[Live site & guide](https://nmaaalhawary.github.io/MCP-Moodle/)** · [**Quick start**](#quick-start) · [**Tools**](#tool-reference) · [**How it works**](#how-it-works-two-pieces) · [**Contributing**](#contributing) · [**Releases**](https://github.com/NmaaAlhawary/MCP-Moodle/releases)</samp>

</div>

---

## Table of contents

- [What it does](#what-it-does)
- [How it works (two pieces)](#how-it-works-two-pieces)
- [Features](#features)
- [Quick start](#quick-start)
  - [1. Install the plugin](#1-install-the-plugin-local_mcpbridge)
  - [2. Enable web services & get a token](#2-enable-web-services--generate-a-token)
  - [3. Run the MCP server](#3-run-the-mcp-server)
  - [4. Connect Claude Desktop](#4-connect-claude-desktop)
- [Tool reference](#tool-reference)
- [Testing from the shell](#testing-from-the-shell)
- [Safety & write mode](#safety--write-mode)
- [Extending it](#extending-it)
- [Contributing](#contributing)
- [License](#license)

---

## What it does

Moodle is the learning platform used by thousands of universities and schools.
**MCP-Moodle** connects it to any [Model Context Protocol](https://modelcontextprotocol.io/)
client — Claude Desktop, Gemini CLI, ChatGPT desktop, Cursor, VS Code, and
more — so an AI can:

- **Read**: list courses, inspect course contents, see enrolled students, quizzes, grades,
  quiz results, activity completion, notifications, and competencies.
- **Build**: create courses, users, categories, groups, enrolments, and *actual content*:
  pages, books, labels, URLs, forums, assignments, quizzes, and six quiz question types —
  or a whole course from one JSON outline with `build_course`.
- **Protect**: back any course up to a real `.mbz` file on your disk and restore it later.
- **Safely**: every write operation is off by default and only enabled with an explicit flag.

## How it works (two pieces)

Moodle's built-in Web Services API can create courses and users, but it has **no
core function to create an activity** (a Page, Book, Quiz…). So this project ships
two parts that work together:

```
┌────────────────┐     MCP tools      ┌──────────────────┐    REST + token    ┌─────────────────┐
│  AI client     │ ◄────────────────► │   moodle-mcp     │ ◄────────────────► │   Moodle site   │
│ (Claude, etc.) │                    │  (Python server) │                    │  + local plugin │
└────────────────┘                    └──────────────────┘                    └─────────────────┘
```

| Piece | Path | Role |
|---|---|---|
| **Moodle plugin** | [`local/mcpbridge/`](https://github.com/NmaaAlhawary/MCP-Moodle/tree/main/local/mcpbridge) | Adds the activity-creation web service functions Moodle core is missing, using Moodle's official `add_moduleinfo()` helper, so it's upgrade-safe (no raw DB writes). |
| **MCP server** | [`moodle-mcp/`](https://github.com/NmaaAlhawary/MCP-Moodle/tree/main/moodle-mcp) | Wraps Moodle's read/write API, core *and* the plugin's new functions, as typed MCP tools for an AI client. |

## Compatibility & authentication

**Works with any Moodle 4.2 or newer**: self-hosted or institutional. There is
nothing site-specific in the code.

**Authentication uses a web service token, not a password.** The MCP server never
sends a username or password, it sends a **token** (a long random string) that an
admin generates in Moodle. The token is tied to one user account and one service
(a fixed list of allowed functions), and it can only do what **that user's
capabilities** permit.

Because the token is a separate auth channel, **it works regardless of how your
users log in**: Manual, LDAP, SAML/SSO (Shibboleth, ADFS), Google/Microsoft
OAuth, CAS, etc. all make no difference. A university Moodle behind SSO works
exactly the same: an admin just creates a token.

**What you need**

| Requirement | Why |
|---|---|
| Admin access (yours or an admin's) | To install the plugin, enable web services, and create the token |
| Web services + REST enabled | The authentication mechanism |
| A user account for the token | The token inherits that user's capabilities |

**Caveats (be honest with yourself here)**

- You must be able to **install a plugin**: full control on self-hosted/institutional Moodle; a locked-down managed host may require the admin to do it.
- Some managed clouds (e.g. **MoodleCloud free tier**) block custom plugins and full web services, activity creation won't work there.
- **Without the plugin**, the MCP server's *core* tools (list/create courses, users, enrolment, grades) still work with a token; you only lose the plugin's activity-creation tools.

## Features

- **86 tools**: reads, a student-assistant layer (deadlines, dashboards, task analysis), core writes, activity creation, quiz results, completion tracking, backup/restore, and a one-call course builder
- **Six quiz question types**: multiple choice, true/false, short answer, essay, numerical (with tolerances), matching
- **Read/write split**: read tools always on; write tools gated behind `MOODLE_ALLOW_WRITE=true`
- **Upgrade-safe plugin**: uses Moodle's own `add_moduleinfo()`, never touches module tables directly
- **Clean error handling**: detects Moodle's JSON-error-on-HTTP-200 quirk and surfaces readable messages
- **Token never logged**: config from environment variables only
- **One token for everything**: read, core writes, and the plugin functions in a single service

---

## Quick start

### 1. Install the plugin (`local_mcpbridge`)

Copy the `local/mcpbridge/` folder into your Moodle so it lives at
`{moodle}/local/mcpbridge/`, then visit **Site administration** as an admin,
Moodle will detect it and prompt you to upgrade. Confirm, and you're done.

### 2. Enable web services & generate a token

In Moodle as an admin:

1. **Enable web services**: *Site administration → Advanced features* → tick **Enable web services**.
2. **Enable REST**: *Server → Web services → Manage protocols* → enable **REST protocol**.
3. **Build the service**: *Server → Web services → External services*. Use the
   ready-made **MCP Bridge Service** the plugin created, then click **Functions**
   and add the core read/write functions you want (list in [Tool reference](#tool-reference)).
4. **Authorise a user** for the service and make sure they have the needed
   capabilities (notably `moodle/course:manageactivities` in the target courses).
5. **Create a token**: *Server → Web services → Manage tokens* → pick the user and
   the **MCP Bridge Service**. Copy it.

> **Verify before you wire up the AI:** run the [test script](#testing-from-the-shell)
> to confirm the plugin and token work end to end.

### 3. Run the MCP server

**Easiest — from PyPI:**

```bash
pip install moodle-mcp-bridge
MOODLE_URL=https://moodle.example.edu MOODLE_TOKEN=your_token moodle-mcp-bridge
```

**Or from a checkout:**

```bash
cd moodle-mcp
python3 -m venv .venv && source .venv/bin/activate
pip install -r requirements.txt
cp .env.example .env        # then edit .env with your URL + token
python server.py
```

| Env var | Meaning |
|---|---|
| `MOODLE_URL` | Base URL, no trailing slash |
| `MOODLE_TOKEN` | The token from step 2 (never logged) |
| `MOODLE_ALLOW_WRITE` | `true` to enable write tools; anything else = read-only |

### 4. Connect Claude Desktop

Add to `claude_desktop_config.json` (pip install):

```json
{
  "mcpServers": {
    "moodle": {
      "command": "moodle-mcp-bridge",
      "env": {
        "MOODLE_URL": "https://moodle.example.edu",
        "MOODLE_TOKEN": "your_token_here",
        "MOODLE_ALLOW_WRITE": "true"
      }
    }
  }
}
```

Running from a checkout instead? Use `"command": "/absolute/path/to/moodle-mcp/.venv/bin/python"` with `"args": ["/absolute/path/to/moodle-mcp/server.py"]`.

### Not just Claude: any MCP client works

MCP is an open protocol, so the same server works with **Gemini CLI, ChatGPT
desktop (developer mode), Cursor, Windsurf, VS Code Copilot agent mode, Cline,
LM Studio**, and anything else that speaks MCP. The config is nearly identical
everywhere — the `moodle-mcp-bridge` command plus the three env vars. Example
for **Gemini CLI** (`~/.gemini/settings.json`):

```json
{
  "mcpServers": {
    "moodle": {
      "command": "moodle-mcp-bridge",
      "env": {
        "MOODLE_URL": "https://moodle.example.edu",
        "MOODLE_TOKEN": "your_token_here",
        "MOODLE_ALLOW_WRITE": "true"
      }
    }
  }
}
```

Tool-use quality depends on the model: with 86 tools, stronger models chain
calls (create course → add section → add quiz) more reliably than small local
ones.

Restart Claude Desktop and the Moodle tools appear.

---

## Tool reference

86 tools, grouped by what they touch. **Access** shows whether a tool is
always available (read) or needs `MOODLE_ALLOW_WRITE=true` (write). Tools
backed by the plugin are marked **plugin**; everything else uses Moodle core
functions.

### Courses & structure

| Tool | What it does | Access |
|---|---|---|
| `list_courses` / `search_courses` | List all courses / search by field | read |
| `get_course_content` | Everything inside a course (sections, activities, files) | read |
| `get_my_courses` | Courses the current user is enrolled in | read |
| `create_course` / `update_course` | Create or update a course | write |
| `create_category` / `delete_category` | Manage course categories | write |
| `create_section` / `delete_section` <sup>plugin</sup> | Manage course sections | write |
| `delete_courses` | Delete courses (irreversible) | write |
| `import_course` | Copy content from one course into another | write |

### Course builder & backup

| Tool | What it does | Access |
|---|---|---|
| `build_course` | Build a whole course — sections, activities, quizzes with questions — from **one JSON outline**, with a per-item build log | write |
| `backup_course` <sup>plugin</sup> | Full course backup, saved as a real `.mbz` on your local disk | write |
| `restore_course` <sup>plugin</sup> | Restore a `.mbz` file into a brand-new course | write |

### Content & activities

| Tool | What it does | Access |
|---|---|---|
| `create_page` / `update_page` <sup>plugin</sup> | HTML page activities | write |
| `create_book` <sup>plugin</sup> | Book with its first chapter | write |
| `create_label` <sup>plugin</sup> | Inline text/label on the course page | write |
| `create_url` <sup>plugin</sup> | External link resource | write |
| `create_forum` / `create_choice` <sup>plugin</sup> | Forum / poll activities | write |
| `create_assignment` <sup>plugin</sup> | Assignment with due date and grade | write |
| `update_activity` / `move_activity` / `delete_activity` <sup>plugin</sup> | Rename, hide, move or delete any activity | write |
| `upload_file` / `download_file` | Transfer files to/from Moodle | write |

### Quizzes & questions

| Tool | What it does | Access |
|---|---|---|
| `list_quizzes` | Quizzes in given courses | read |
| `get_quiz_results` | A user's attempts + best grade for a quiz | read |
| `get_quiz_attempt_review` | Finished attempt, question by question | read |
| `create_quiz` <sup>plugin</sup> | Quiz container with timing/grade settings | write |
| `add_quiz_question` <sup>plugin</sup> | Multiple-choice question | write |
| `add_truefalse_question` <sup>plugin</sup> | True/false question | write |
| `add_shortanswer_question` <sup>plugin</sup> | Short-answer question | write |
| `add_essay_question` <sup>plugin</sup> | Essay (manually graded) question | write |
| `add_numerical_question` <sup>plugin</sup> | Numerical question with tolerances | write |
| `add_matching_question` <sup>plugin</sup> | Matching-pairs question | write |

### Users, enrolment & groups

| Tool | What it does | Access |
|---|---|---|
| `get_enrolled_users` | Who is enrolled in a course | read |
| `create_user` / `update_user` / `delete_users` | Manage user accounts | write |
| `enrol_user` / `enrol_users` / `unenrol_user` | Manual enrolment (single or bulk) | write |
| `assign_role` / `unassign_role` | Role assignments | write |
| `create_group` / `add_group_members` | Course groups | write |
| `create_cohort` / `add_cohort_members` | Site-wide cohorts | write |

### Communication

| Tool | What it does | Access |
|---|---|---|
| `get_course_announcements` | News-forum announcements | read |
| `get_notifications` | Unread count + recent notifications | read |
| `send_message` | Direct message to a user | write |
| `start_forum_discussion` / `reply_forum_post` | Post in forums | write |

### Assignments & grading

| Tool | What it does | Access |
|---|---|---|
| `get_assignments` / `get_assignment_status` | Assignments and submission/grading state | read |
| `get_upcoming_deadlines` / `get_overdue_assignments` | Deadline tracking | read |
| `analyze_assignment` / `extract_assignment_requirements` | Requirements + context for one assignment | read |
| `find_relevant_materials` / `decompose_task` / `create_implementation_plan` | Scaffolds for working on an assignment | read |
| `grade_assignment` | Save a grade **with feedback text** to the gradebook | write |

### Grades, progress & completion

| Tool | What it does | Access |
|---|---|---|
| `get_grades` / `get_user_grades` | Grade overview or per-course detail | read |
| `get_course_progress` / `get_course_health` | Progress and at-a-glance course health | read |
| `get_activity_completion` | Which activities a user completed | read |
| `get_course_completion_status` | Whole-course completion criteria | read |
| `list_course_competencies` / `get_learning_plans` | Competency framework data | read |
| `mark_activity_complete` | Tick your own manual completion box | write |
| `override_activity_completion` | Teacher: set a student's completion state | write |

### Planning & insights

| Tool | What it does | Access |
|---|---|---|
| `get_upcoming_events` | Calendar events | read |
| `get_recent_activity` | Updates across courses since a time | read |
| `get_study_load` | Assignment distribution by week | read |
| `get_actionable_tasks` | Prioritized to-do list by urgency | read |
| `semester_dashboard` / `daily_briefing` / `weekly_review` | Combined snapshots | read |

### Search & assist

| Tool | What it does | Access |
|---|---|---|
| `verify_connection` | Site info + available functions (start here) | read |
| `search_course_materials` | Search across course materials | read |
| `ask_moodle` | Route a natural-language question to the right data | read |

> **Quizzes:** the six `add_*_question` tools use Moodle's Question Bank API
> (multiple choice, true/false, short answer, essay, numerical, matching).
> That API is version-sensitive — tested on Moodle 5.0; test on your version first.

> **Missing function?** A tool whose web service function isn't in the token's
> service returns a clean "access control exception" — add the function to
> **MCP Bridge Service** and retry. Installing plugin 1.6.0+ registers the
> complete list automatically.

---

## Testing from the shell

Confirm the plugin + token work before wiring up the AI:

```bash
cd moodle-mcp

# Read-only checks (site info + course list):
MOODLE_URL=https://moodle.example.edu MOODLE_TOKEN=xxxx ./test_rest.sh

# Full write test, creates a page, label, url, book, quiz + question in COURSEID:
MOODLE_URL=https://moodle.example.edu MOODLE_TOKEN=xxxx COURSEID=2 ./test_rest.sh --write
```

A successful create returns e.g. `{"cmid": 47, "instanceid": 12}`. A Moodle error
(still HTTP 200) returns a JSON object with an `exception` field, which the server
surfaces as a clean message.

---

## Safety & write mode

For a full hardening checklist (dedicated service account, scoped role, HTTPS, token expiry), see **[SECURITY.md](https://github.com/NmaaAlhawary/MCP-Moodle/blob/main/SECURITY.md)**.

- **Writes hit live data.** Every write tool creates or changes real records. There is no dry-run.
- **Writes are gated.** If `MOODLE_ALLOW_WRITE` is not exactly `true`, the server registers **read tools only**: writes cannot be exposed by accident.
- **The token is the blast radius.** Bind it to a dedicated service account with only the capabilities and courses you need. A site-admin token lets the AI do anything an admin can.
- **The token is never logged**: read from the environment, sent only to Moodle over HTTPS.
- **Start read-only.** Turn on write mode deliberately, on a test course, and confirm the shell test passes first.

---

## Extending it

Adding a new capability (e.g. `create_assignment`, `create_forum`) is copy-paste-modify.
Each plugin function follows the same three-method shape, see
[`create_page.php`](https://github.com/NmaaAlhawary/MCP-Moodle/blob/main/local/mcpbridge/classes/external/create_page.php) as the template,
and [CONTRIBUTING.md](https://github.com/NmaaAlhawary/MCP-Moodle/blob/main/CONTRIBUTING.md) for the full recipe.

---

## Contributing

Contributions are very welcome, this is built to be extended!

1. **Fork** the repo (top-right **Fork** button).
2. Create a branch: `git checkout -b feature/my-thing`.
3. Make your change and test it (`php -l`, `py_compile`, ideally against a real Moodle).
4. Push and open a **Pull Request** to `main`.

See **[CONTRIBUTING.md](https://github.com/NmaaAlhawary/MCP-Moodle/blob/main/CONTRIBUTING.md)** for the full workflow, coding standards,
and the pattern for adding a new function. Please also read our
[Code of Conduct](https://github.com/NmaaAlhawary/MCP-Moodle/blob/main/CODE_OF_CONDUCT.md). Found a bug or want a feature? Open an
[issue](https://github.com/NmaaAlhawary/MCP-Moodle/issues).

---

## License

[MIT](https://github.com/NmaaAlhawary/MCP-Moodle/blob/main/LICENSE), free to use, modify, and distribute.

<div align="center">
<sub>Built for educators, admins, and anyone who wants to talk to Moodle instead of clicking through it.</sub>
</div>
