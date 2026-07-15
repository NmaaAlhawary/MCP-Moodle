#!/usr/bin/env bash
#
# test_rest.sh — exercise the Moodle REST endpoint end to end.
#
# Runs a read check, then (optionally) every local_mcpbridge write function
# against a real course, printing each response. Use this to confirm the plugin
# and token work before wiring up the MCP server / AI client.
#
# Usage:
#   MOODLE_URL=https://moodle.example.edu \
#   MOODLE_TOKEN=xxxx \
#   COURSEID=2 \
#   ./test_rest.sh [--write]
#
#   --write   also run the create_* functions (they modify live data).
#
# Reads MOODLE_URL / MOODLE_TOKEN / COURSEID from the environment (or a sibling
# .env file if present). Pretty-prints with jq when available.

set -euo pipefail

# Load .env from this script's directory if present (without clobbering env vars).
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [[ -f "$SCRIPT_DIR/.env" ]]; then
  # shellcheck disable=SC1091
  set -a; source "$SCRIPT_DIR/.env"; set +a
fi

: "${MOODLE_URL:?Set MOODLE_URL (e.g. https://moodle.example.edu)}"
: "${MOODLE_TOKEN:?Set MOODLE_TOKEN}"

WRITE=0
[[ "${1:-}" == "--write" ]] && WRITE=1

ENDPOINT="${MOODLE_URL%/}/webservice/rest/server.php"

if command -v jq >/dev/null 2>&1; then
  PP() { jq .; }
else
  PP() { cat; echo; }   # jq not installed — print raw JSON.
fi

# call <wsfunction> [--data-urlencode k=v ...]
call() {
  local fn="$1"; shift
  echo "==> $fn"
  curl -sS "$ENDPOINT" \
    --data-urlencode "wstoken=${MOODLE_TOKEN}" \
    --data-urlencode "wsfunction=${fn}" \
    --data-urlencode "moodlewsrestformat=json" \
    "$@" | PP
  echo
}

echo "### READ: verify_connection ###"
call core_webservice_get_site_info

echo "### READ: list_courses ###"
call core_course_get_courses

if [[ "$WRITE" -eq 0 ]]; then
  echo "Read checks done. Re-run with --write (and COURSEID set) to test activity creation."
  exit 0
fi

: "${COURSEID:?Set COURSEID to a course you can edit (required for --write)}"
echo "### WRITE MODE — creating activities in course $COURSEID ###"

echo "### create_page ###"
call local_mcpbridge_create_page \
  --data-urlencode "courseid=${COURSEID}" \
  --data-urlencode "section=0" \
  --data-urlencode "name=MCP Test Page" \
  --data-urlencode "content=<h2>Hello from test_rest.sh</h2><p>It works.</p>"

echo "### create_label ###"
call local_mcpbridge_create_label \
  --data-urlencode "courseid=${COURSEID}" \
  --data-urlencode "section=0" \
  --data-urlencode "content=<strong>Inline label</strong> created via REST."

echo "### create_url ###"
call local_mcpbridge_create_url \
  --data-urlencode "courseid=${COURSEID}" \
  --data-urlencode "section=0" \
  --data-urlencode "name=Moodle Docs" \
  --data-urlencode "externalurl=https://docs.moodle.org"

echo "### create_book (+ first chapter) ###"
call local_mcpbridge_create_book \
  --data-urlencode "courseid=${COURSEID}" \
  --data-urlencode "section=0" \
  --data-urlencode "name=MCP Test Book" \
  --data-urlencode "intro=A book created via REST." \
  --data-urlencode "chaptertitle=Chapter One" \
  --data-urlencode "chaptercontent=<p>First chapter body.</p>"

echo "### create_quiz (container only) — capturing cmid for the question test ###"
QUIZ_JSON=$(curl -sS "$ENDPOINT" \
  --data-urlencode "wstoken=${MOODLE_TOKEN}" \
  --data-urlencode "wsfunction=local_mcpbridge_create_quiz" \
  --data-urlencode "moodlewsrestformat=json" \
  --data-urlencode "courseid=${COURSEID}" \
  --data-urlencode "section=0" \
  --data-urlencode "name=MCP Test Quiz")
echo "$QUIZ_JSON" | PP

echo "### add_quiz_question (stretch goal — multichoice) ###"
if command -v jq >/dev/null 2>&1 && echo "$QUIZ_JSON" | jq -e '.cmid' >/dev/null 2>&1; then
  QUIZ_CMID=$(echo "$QUIZ_JSON" | jq -r '.cmid')
  call local_mcpbridge_add_quiz_question \
    --data-urlencode "quizcmid=${QUIZ_CMID}" \
    --data-urlencode "name=Capital of France" \
    --data-urlencode "questiontext=<p>What is the capital of France?</p>" \
    --data-urlencode "single=1" \
    --data-urlencode "defaultmark=1" \
    --data-urlencode "answers[0][text]=Paris" \
    --data-urlencode "answers[0][fraction]=1.0" \
    --data-urlencode "answers[1][text]=London" \
    --data-urlencode "answers[1][fraction]=0" \
    --data-urlencode "answers[2][text]=Berlin" \
    --data-urlencode "answers[2][fraction]=0"
else
  echo "(skipped — need jq to parse the quiz cmid, or create_quiz did not return one)"
fi

echo "All write tests attempted. Check the course page in Moodle to confirm."
