# Moodle MCP — Server + Companion Plugin

Let an AI client (Claude Desktop, etc.) **do anything in a Moodle instance**: read
courses, enrol users, upload files, and actually *build* content — create courses,
pages, books, labels, URLs, and quizzes.

It ships as **two pieces that work together**:

| Piece | What it is |
|---|---|
| **`local/mcpbridge/`** | A Moodle plugin that adds the activity-creation web service functions Moodle core is missing. |
| **`moodle-mcp/`** | An MCP server (Python) that wraps Moodle's read/write API — including the plugin's new functions — as AI tools. |

## Why two pieces

Moodle's Web Services API can create courses, users, enrolments, categories, groups,
and upload files — but it has **no core function to create an activity** (a Page,
Book, Label, URL, or Quiz). The plugin fills that gap by registering new web service
functions that use Moodle's official `add_moduleinfo()` helper (the same code path the
web UI uses — upgrade-safe, no raw table inserts). The MCP server then calls both core
functions and the plugin's functions through a single token.

---

## Part A — Install the plugin (`local_mcpbridge`)

1. Copy the `local/mcpbridge/` folder into your Moodle install so it lives at
   `{moodle}/local/mcpbridge/`.
2. Visit **Site administration** as an admin — Moodle detects the new plugin and
   prompts you to upgrade the database. Confirm it.
3. Done. The plugin registers five new web service functions and one service
   (**MCP Bridge Service**).

### Functions the plugin adds

| Function | Creates |
|---|---|
| `local_mcpbridge_create_page` | A Page activity (HTML content) |
| `local_mcpbridge_create_book` | A Book + its first chapter |
| `local_mcpbridge_create_label` | A Label (inline text/HTML) |
| `local_mcpbridge_create_url` | A URL resource |
| `local_mcpbridge_create_quiz` | An empty Quiz (container/settings only) |
| `local_mcpbridge_add_quiz_question` | A multiple-choice question added to an existing quiz (stretch goal) |

> **Quizzes:** `create_quiz` makes the quiz container. `add_quiz_question` is the
> stretch goal — it uses Moodle's Question Bank API to add a multiple-choice question
> to a quiz. That API is heavier and more version-sensitive than activity creation, so
> only single/multi-answer multiple-choice is supported; verify it on your Moodle
> version before relying on it.

---

## Part B — Enable Web Services & generate a token

In Moodle as an admin:

1. **Enable web services**
   *Site administration → Advanced features* → tick **Enable web services** → Save.
2. **Enable the REST protocol**
   *Site administration → Server → Web services → Manage protocols* → enable **REST**.
3. **Build one service with everything**
   *Site administration → Server → Web services → External services*.
   - You can use the ready-made **MCP Bridge Service** the plugin created, then click
     **Functions** and **add the core functions** you want the AI to use (see list
     below). This gives you one service and one token covering read, core writes, and
     activity creation.
   - Recommended core functions to add:
     `core_webservice_get_site_info`, `core_course_get_courses`,
     `core_course_get_courses_by_field`, `core_course_get_contents`,
     `core_enrol_get_enrolled_users`, `mod_quiz_get_quizzes_by_courses`,
     `gradereport_user_get_grade_items`, `core_course_create_courses`,
     `core_course_create_categories`, `core_user_create_users`,
     `enrol_manual_enrol_users`, `core_group_create_groups`, `core_files_upload`.
4. **Authorise a user** for the service (it is restricted-users by default) and make
   sure that user has the capabilities the write functions need — notably
   `moodle/course:manageactivities` in the target courses.
5. **Create a token**
   *Site administration → Server → Web services → Manage tokens* → **Create token** →
   pick the user and the **MCP Bridge Service**. Copy the token.

### Verify the plugin from a raw REST call (do this before wiring the AI)

```bash
curl "https://moodle.example.edu/webservice/rest/server.php" \
  --data-urlencode "wstoken=YOUR_TOKEN" \
  --data-urlencode "wsfunction=local_mcpbridge_create_page" \
  --data-urlencode "moodlewsrestformat=json" \
  --data-urlencode "courseid=2" \
  --data-urlencode "section=0" \
  --data-urlencode "name=Welcome" \
  --data-urlencode "content=<h2>Hello from MCP</h2><p>It works.</p>"
```

Expected success response:

```json
{"cmid": 47, "instanceid": 12}
```

A Moodle error (still HTTP 200) looks like:

```json
{"exception":"required_capability_exception","errorcode":"nopermissions",
 "message":"Sorry, but you do not currently have permissions to do that (Manage activities)."}
```

The MCP server detects that `exception` field and surfaces it as a clean error.

---

## Part C — Run the MCP server

```bash
cd moodle-mcp
python3 -m venv .venv && source .venv/bin/activate
pip install -r requirements.txt
cp .env.example .env      # then edit .env
```

Environment variables (`.env` or your shell):

| Var | Meaning |
|---|---|
| `MOODLE_URL` | Base URL, no trailing slash |
| `MOODLE_TOKEN` | The token from Part B (never logged) |
| `MOODLE_ALLOW_WRITE` | `true` to enable write tools; anything else = read-only |

Run it:

```bash
MOODLE_URL=https://moodle.example.edu MOODLE_TOKEN=xxxx MOODLE_ALLOW_WRITE=true \
  python server.py
```

### Claude Desktop config

Add to `claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "moodle": {
      "command": "/absolute/path/to/moodle-mcp/.venv/bin/python",
      "args": ["/absolute/path/to/moodle-mcp/server.py"],
      "env": {
        "MOODLE_URL": "https://moodle.example.edu",
        "MOODLE_TOKEN": "your_token_here",
        "MOODLE_ALLOW_WRITE": "true"
      }
    }
  }
}
```

---

## Tools

### Read (always on)

| Tool | `wsfunction` |
|---|---|
| `verify_connection` | `core_webservice_get_site_info` |
| `list_courses` | `core_course_get_courses` |
| `search_courses` | `core_course_get_courses_by_field` |
| `get_course_content` | `core_course_get_contents` |
| `get_enrolled_users` | `core_enrol_get_enrolled_users` |
| `list_quizzes` | `mod_quiz_get_quizzes_by_courses` |
| `get_user_grades` | `gradereport_user_get_grade_items` |

### Write (only when `MOODLE_ALLOW_WRITE=true`)

| Tool | `wsfunction` | Source |
|---|---|---|
| `create_course` | `core_course_create_courses` | core |
| `create_category` | `core_course_create_categories` | core |
| `create_user` | `core_user_create_users` | core |
| `enrol_user` | `enrol_manual_enrol_users` | core |
| `create_group` | `core_group_create_groups` | core |
| `upload_file` | `core_files_upload` | core |
| `create_page` | `local_mcpbridge_create_page` | **plugin** |
| `create_book` | `local_mcpbridge_create_book` | **plugin** |
| `create_label` | `local_mcpbridge_create_label` | **plugin** |
| `create_url` | `local_mcpbridge_create_url` | **plugin** |
| `create_quiz` | `local_mcpbridge_create_quiz` | **plugin** |
| `add_quiz_question` | `local_mcpbridge_add_quiz_question` | **plugin** |

---

## Testing from the shell (`test_rest.sh`)

Before wiring the AI client, confirm the plugin + token work end to end:

```bash
cd moodle-mcp
# Read-only checks (site info + course list):
MOODLE_URL=https://moodle.example.edu MOODLE_TOKEN=xxxx ./test_rest.sh

# Full write test — creates a page, label, url, book, quiz, and a quiz question
# in COURSEID (⚠️ modifies live data):
MOODLE_URL=https://moodle.example.edu MOODLE_TOKEN=xxxx COURSEID=2 ./test_rest.sh --write
```

It reads a sibling `.env` if present, and pretty-prints with `jq` when installed.
The `--write` run captures the new quiz's `cmid` and feeds it straight into
`add_quiz_question`, so you see the whole activity-creation path in one pass.

---

## ⚠️ Safety note — write mode

- **Writes hit live Moodle data.** Every write tool creates or changes real records
  (courses, users, enrolments, activities, files). There is no dry-run.
- **Writes are gated.** If `MOODLE_ALLOW_WRITE` is not exactly `true`, the server
  registers **read tools only** — write tools cannot be exposed by accident.
- **The token is the blast radius.** Bind it to a dedicated service account with only
  the capabilities you actually need, and restrict it to the intended courses. A token
  with site-admin rights lets the AI do anything an admin can.
- **The token is never logged.** It is read from the environment and sent only as a
  POST field to Moodle over HTTPS.
- Start read-only. Turn on write mode deliberately, on a test course, and confirm the
  raw REST call from Part B works before letting an AI client drive it.

## License

MIT — see [`moodle-mcp/LICENSE`](moodle-mcp/LICENSE).
