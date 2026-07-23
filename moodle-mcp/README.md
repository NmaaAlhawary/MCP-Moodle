# moodle-mcp-bridge

Talk to Moodle. An [MCP](https://modelcontextprotocol.io/) server that exposes a
Moodle site as **86 typed tools** for AI clients like Claude Desktop and Claude
Code — read courses, grades, deadlines and quiz results; build courses,
sections, quizzes (six question types), assignments and content; back courses
up to real `.mbz` files and restore them.

> *"Create a course called Biology 101, add a welcome page, and enrol these five students."* — and it actually happens.

## Install

```bash
pip install moodle-mcp-bridge
```

## Configure

Three environment variables (or a `.env` file in the working directory):

```bash
MOODLE_URL=https://moodle.example.edu   # your Moodle site
MOODLE_TOKEN=xxxxxxxxxxxxxxxx           # a web service token
MOODLE_ALLOW_WRITE=true                 # omit for read-only (the default)
```

Read tools are always available. Write tools (course/content creation,
enrolment, grading, backup/restore) only register when `MOODLE_ALLOW_WRITE`
is explicitly `true`, so a misconfiguration can never silently enable writes.

## Connect Claude Desktop

```json
{
  "mcpServers": {
    "moodle": {
      "command": "moodle-mcp-bridge",
      "env": {
        "MOODLE_URL": "https://moodle.example.edu",
        "MOODLE_TOKEN": "your-token",
        "MOODLE_ALLOW_WRITE": "true"
      }
    }
  }
}
```

## The companion Moodle plugin

Core Moodle web services cannot create activities (pages, quizzes, questions…).
The companion plugin **`local_mcpbridge`** adds those functions — grab it from
the [GitHub releases](https://github.com/NmaaAlhawary/MCP-Moodle/releases) and
install it on your Moodle (4.2+). Without the plugin, all core-backed tools
(courses, users, enrolment, grades, completion, messaging) still work.

## Documentation

Full setup guide, tool reference and security model:
**https://nmaaalhawary.github.io/MCP-Moodle/**

MIT licensed. Source: https://github.com/NmaaAlhawary/MCP-Moodle
