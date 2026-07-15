# Security & hardening guide

The MCP server holds a Moodle web service **token**. Anyone with that token can do
whatever the token's user + service allow. Treat it like a password. This guide
covers running MCP-Moodle safely — especially on a **real, hosted Moodle**.

## Threat model in one line

The token is the blast radius. Scope the token's **user**, its **service**
(function list), and its **transport** (HTTPS) so a leaked token can do as little
as possible.

---

## Do these before any hosted / production use

### 1. Use HTTPS — always

A token sent over plain `http://` can be sniffed on the network. Set
`MOODLE_URL=https://…` and make sure the site has a valid certificate. (On a
local `localhost` dev site this doesn't matter — traffic never leaves the machine.)

### 2. Never use the site-admin account for the token

The primary admin can change site config, install plugins, and manage tokens — far
more than this tool needs. Create a **dedicated service account** instead.

### 3. Give the service account a scoped role, not admin

Two options, from simplest to strictest:

- **Manager role** (pragmatic): covers all read/write/activity functions, but
  *cannot* change site settings, install plugins, or manage web services. Assign at
  a **course category** context rather than system context to limit it to specific
  courses.
- **Custom role** (strictest): create a role with only the capabilities the
  functions you actually use require — e.g. `moodle/course:manageactivities` for
  activity creation, `moodle/course:create` for `create_course`,
  `moodle/user:create` for `create_user`, `enrol/manual:enrol` for enrolment. Plus
  **`webservice/rest:use`** (required to call the REST API at all).

### 4. Keep the service "authorised users only"

On the service (*Server → Web services → External services → MCP Bridge Service*),
keep **Authorised users only** ticked and add just the service account. This means
a leaked token for any other user still can't use the service.

### 5. Only add the functions you need to the service

The fewer functions in the service, the less a leaked token can do. If you only
need read + page creation, don't add `create_user` or `create_course`.

### 6. Set the token to expire, and restrict its IP

When creating the token: set **Valid until** to a real date, and set **IP
restriction** to the address the MCP server runs from. Rotate tokens periodically.

### 7. Consider read-only for everyday use

Run the MCP server with `MOODLE_ALLOW_WRITE=false` (or unset). Only the read tools
register — the AI physically cannot create or change anything. Turn on write mode
deliberately, and ideally with a separate token bound to a read-only service.

---

## Keep the token out of git

- The token lives in `moodle-mcp/.env` (gitignored) and your MCP client config —
  **never** in a tracked file, screenshot, or issue.
- Verify it never leaked:
  ```bash
  git grep -n "YOUR_TOKEN" $(git rev-list --all)   # should print nothing
  ```
- If it ever leaks: delete it in *Server → Web services → Manage tokens* and issue a
  new one. Deleting a token is instant and irreversible for that string.

---

## Reference: hardened setup via CLI

Run from your Moodle root (adjust the path). This creates a dedicated Manager
service account, re-locks the service, and issues a 90-day token. For the strictest
setup, replace the Manager assignment with a custom role and scope it to a category.

```php
<?php
define('CLI_SCRIPT', true);
require('/path/to/moodle/config.php');
require_once($CFG->dirroot . '/user/lib.php');

$svc = $DB->get_record('external_services', ['shortname' => 'local_mcpbridge_service'], '*', MUST_EXIST);
$ctx = context_system::instance();   // or a category context to scope it

// Dedicated, non-admin account.
$u = (object)[
    'auth' => 'manual', 'confirmed' => 1, 'mnethostid' => $CFG->mnet_localhost_id,
    'username' => 'mcpservice', 'password' => 'Change!' . random_string(16),
    'firstname' => 'MCP', 'lastname' => 'Service', 'email' => 'mcpservice@example.edu',
];
$uid = $DB->record_exists('user', ['username' => 'mcpservice'])
    ? $DB->get_field('user', 'id', ['username' => 'mcpservice'])
    : user_create_user($u, false, false);

// Manager role + the REST capability (Manager lacks webservice/rest:use by default).
$rid = $DB->get_field('role', 'id', ['shortname' => 'manager']);
role_assign($rid, $uid, $ctx->id);
assign_capability('webservice/rest:use', CAP_ALLOW, $rid, $ctx->id, true);

// Re-lock service to this user only.
$DB->set_field('external_services', 'restrictedusers', 1, ['id' => $svc->id]);
if (!$DB->record_exists('external_services_users', ['externalserviceid' => $svc->id, 'userid' => $uid])) {
    $DB->insert_record('external_services_users',
        (object)['externalserviceid' => $svc->id, 'userid' => $uid, 'timecreated' => time()]);
}

// Expiring token.
$token = \core_external\util::generate_token(
    EXTERNAL_TOKEN_PERMANENT, $svc, $uid, $ctx, time() + 90 * 86400);
purge_all_caches();
echo "TOKEN=$token\n";
```

## Reporting a vulnerability

Found a way to bypass the capability checks or escalate via the plugin? Please
**do not** open a public issue — contact the maintainer directly.
