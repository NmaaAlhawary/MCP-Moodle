"""moodle-mcp — an MCP server that exposes a Moodle instance as tools.

Read tools are always registered. Write tools (course/user/activity creation,
enrolment, file upload) are only registered when MOODLE_ALLOW_WRITE=true, so a
misconfiguration can never silently expose destructive operations.

Config comes from environment variables only — the token is never logged.

    MOODLE_URL          e.g. https://moodle.example.edu
    MOODLE_TOKEN        a web service token
    MOODLE_ALLOW_WRITE  "true" to enable write tools (default: read-only)

All calls hit:
    {MOODLE_URL}/webservice/rest/server.php
        ?wstoken=...&wsfunction=...&moodlewsrestformat=json
"""

from __future__ import annotations

import os
from typing import Any

import httpx
from mcp.server.fastmcp import FastMCP

# --------------------------------------------------------------------------- #
# Configuration (env vars only — never hard-code or log the token).
# --------------------------------------------------------------------------- #

MOODLE_URL = os.environ.get("MOODLE_URL", "").rstrip("/")
MOODLE_TOKEN = os.environ.get("MOODLE_TOKEN", "")
ALLOW_WRITE = os.environ.get("MOODLE_ALLOW_WRITE", "").strip().lower() == "true"

REST_ENDPOINT = f"{MOODLE_URL}/webservice/rest/server.php"

mcp = FastMCP("moodle-mcp")


class MoodleError(Exception):
    """Raised when Moodle returns an error, even on HTTP 200."""


# --------------------------------------------------------------------------- #
# Shared request helper.
# --------------------------------------------------------------------------- #

async def _call(wsfunction: str, params: dict[str, Any] | None = None) -> Any:
    """Call a Moodle web service function and return parsed JSON.

    Moodle returns errors as a JSON object containing "exception"/"errorcode"
    even with an HTTP 200 status, so we detect and raise those as clean errors.
    The token is passed as a POST field and is never logged.
    """
    if not MOODLE_URL or not MOODLE_TOKEN:
        raise MoodleError(
            "MOODLE_URL and MOODLE_TOKEN must be set in the environment."
        )

    data: dict[str, Any] = {
        "wstoken": MOODLE_TOKEN,
        "wsfunction": wsfunction,
        "moodlewsrestformat": "json",
    }
    if params:
        data.update(_flatten(params))

    async with httpx.AsyncClient(timeout=60.0) as client:
        resp = await client.post(REST_ENDPOINT, data=data)
        resp.raise_for_status()

        # Some functions (e.g. deletes) legitimately return null.
        if not resp.content:
            return None
        try:
            payload = resp.json()
        except ValueError as exc:
            raise MoodleError(f"Non-JSON response from Moodle: {resp.text[:300]}") from exc

    if isinstance(payload, dict) and "exception" in payload:
        code = payload.get("errorcode", "unknown")
        message = payload.get("message", "Unknown Moodle error")
        debug = payload.get("debuginfo")
        detail = f" ({debug})" if debug else ""
        raise MoodleError(f"Moodle error [{code}]: {message}{detail}")

    return payload


def _flatten(params: dict[str, Any], prefix: str = "") -> dict[str, str]:
    """Flatten nested dict/list params into Moodle's PHP-style form encoding.

    Moodle expects e.g. courses[0][fullname]=... rather than JSON bodies.
    """
    flat: dict[str, str] = {}
    for key, value in params.items():
        composite = f"{prefix}[{key}]" if prefix else str(key)
        _flatten_value(composite, value, flat)
    return flat


def _flatten_value(key: str, value: Any, flat: dict[str, str]) -> None:
    if isinstance(value, dict):
        for k, v in value.items():
            _flatten_value(f"{key}[{k}]", v, flat)
    elif isinstance(value, (list, tuple)):
        for i, item in enumerate(value):
            _flatten_value(f"{key}[{i}]", item, flat)
    elif isinstance(value, bool):
        flat[key] = "1" if value else "0"
    elif value is not None:
        flat[key] = str(value)


# --------------------------------------------------------------------------- #
# READ TOOLS (always registered).
# --------------------------------------------------------------------------- #

@mcp.tool()
async def verify_connection() -> dict:
    """Verify the token/URL work and return Moodle site info.

    Read-only. Call this first to confirm connectivity and see which site,
    version, and user the token is bound to.
    """
    return await _call("core_webservice_get_site_info")


@mcp.tool()
async def list_courses() -> list:
    """List all courses on the site the token can see.

    Read-only. Returns id, fullname, shortname, categoryid, summary, etc.
    """
    return await _call("core_course_get_courses")


@mcp.tool()
async def search_courses(field: str, value: str) -> dict:
    """Search courses by a single field.

    Read-only. `field` is one of: id, ids, shortname, idnumber, category.
    Example: field="shortname", value="CS101".
    """
    return await _call("core_course_get_courses_by_field", {"field": field, "value": value})


@mcp.tool()
async def get_course_content(courseid: int) -> list:
    """Get the full section/activity structure of one course.

    Read-only. Returns sections, each with the modules (activities/resources)
    inside it — useful for seeing what already exists before adding content.
    """
    return await _call("core_course_get_contents", {"courseid": courseid})


@mcp.tool()
async def get_enrolled_users(courseid: int) -> list:
    """List users enrolled in a course.

    Read-only. Returns each user's id, fullname, email, and roles.
    """
    return await _call("core_enrol_get_enrolled_users", {"courseid": courseid})


@mcp.tool()
async def list_quizzes(courseids: list[int]) -> dict:
    """List quizzes in one or more courses.

    Read-only. Pass a list of course IDs.
    """
    return await _call("mod_quiz_get_quizzes_by_courses", {"courseids": courseids})


@mcp.tool()
async def get_user_grades(courseid: int, userid: int = 0) -> dict:
    """Get grade items for a user in a course.

    Read-only. If userid is 0, returns grades for the token's own user where
    permitted. Otherwise returns the specified user's grade items.
    """
    params: dict[str, Any] = {"courseid": courseid}
    if userid:
        params["userid"] = userid
    return await _call("gradereport_user_get_grade_items", params)


# --------------------------------------------------------------------------- #
# WRITE TOOLS (registered only when MOODLE_ALLOW_WRITE=true).
# --------------------------------------------------------------------------- #

def _register_write_tools() -> None:
    """Register write tools. Called only when writes are enabled."""

    # --- Core writes ------------------------------------------------------- #

    @mcp.tool()
    async def create_course(
        fullname: str,
        shortname: str,
        categoryid: int = 1,
        summary: str = "",
        visible: int = 1,
    ) -> list:
        """⚠️ WRITES LIVE DATA. Create a new course.

        Creates a real course on the Moodle site. `shortname` must be unique.
        `categoryid` must be an existing category (1 is usually the default).
        Returns the new course id and shortname.
        """
        course = {
            "fullname": fullname,
            "shortname": shortname,
            "categoryid": categoryid,
            "summary": summary,
            "summaryformat": 1,
            "visible": visible,
        }
        return await _call("core_course_create_courses", {"courses": [course]})

    @mcp.tool()
    async def create_category(name: str, parent: int = 0, description: str = "") -> list:
        """⚠️ WRITES LIVE DATA. Create a course category.

        `parent` 0 = top level. Returns the new category id and name.
        """
        category = {
            "name": name,
            "parent": parent,
            "description": description,
            "descriptionformat": 1,
        }
        return await _call("core_course_create_categories", {"categories": [category]})

    @mcp.tool()
    async def create_user(
        username: str,
        password: str,
        firstname: str,
        lastname: str,
        email: str,
        auth: str = "manual",
    ) -> list:
        """⚠️ WRITES LIVE DATA. Create a new user account.

        Creates a real login on the site. Password must meet the site's policy.
        Returns the new user id and username.
        """
        user = {
            "username": username,
            "password": password,
            "firstname": firstname,
            "lastname": lastname,
            "email": email,
            "auth": auth,
        }
        return await _call("core_user_create_users", {"users": [user]})

    @mcp.tool()
    async def enrol_user(userid: int, courseid: int, roleid: int = 5) -> None:
        """⚠️ WRITES LIVE DATA. Enrol a user in a course (manual enrolment).

        `roleid` defaults to 5 (Student). Common roles: 3=Teacher, 4=Non-editing
        teacher, 5=Student. Requires the manual enrolment method to be enabled
        in the course. Returns null on success.
        """
        enrolment = {"userid": userid, "courseid": courseid, "roleid": roleid}
        return await _call("enrol_manual_enrol_users", {"enrolments": [enrolment]})

    @mcp.tool()
    async def create_group(courseid: int, name: str, description: str = "") -> list:
        """⚠️ WRITES LIVE DATA. Create a group inside a course.

        Returns the new group id and name.
        """
        group = {
            "courseid": courseid,
            "name": name,
            "description": description,
            "descriptionformat": 1,
        }
        return await _call("core_group_create_groups", {"groups": [group]})

    @mcp.tool()
    async def upload_file(
        filename: str,
        filecontent_base64: str,
        contextid: int = 0,
        component: str = "user",
        filearea: str = "draft",
        itemid: int = 0,
        filepath: str = "/",
    ) -> Any:
        """⚠️ WRITES LIVE DATA. Upload a file into Moodle's file storage.

        `filecontent_base64` is the base64-encoded file bytes. By default the
        file lands in the calling user's draft area (component=user,
        filearea=draft) — the returned itemid can then be attached to an
        activity. Returns the stored file descriptor(s).
        """
        params = {
            "contextid": contextid or 0,
            "component": component,
            "filearea": filearea,
            "itemid": itemid,
            "filepath": filepath,
            "filename": filename,
            "filecontent": filecontent_base64,
        }
        return await _call("core_files_upload", params)

    # --- Plugin writes (local_mcpbridge) ----------------------------------- #

    @mcp.tool()
    async def create_page(
        courseid: int,
        name: str,
        content: str,
        section: int = 0,
        intro: str = "",
        visible: int = 1,
    ) -> dict:
        """⚠️ WRITES LIVE DATA. Create a Page activity (HTML) in a course.

        Requires the local_mcpbridge plugin on the Moodle site. `content` is the
        page body (HTML). Returns the new cmid and page instance id.
        """
        return await _call(
            "local_mcpbridge_create_page",
            {
                "courseid": courseid,
                "section": section,
                "name": name,
                "content": content,
                "intro": intro,
                "visible": visible,
            },
        )

    @mcp.tool()
    async def create_book(
        courseid: int,
        name: str,
        chaptertitle: str,
        chaptercontent: str,
        section: int = 0,
        intro: str = "",
        visible: int = 1,
    ) -> dict:
        """⚠️ WRITES LIVE DATA. Create a Book activity plus its first chapter.

        Requires the local_mcpbridge plugin. Returns cmid, book instance id, and
        the first chapter id.
        """
        return await _call(
            "local_mcpbridge_create_book",
            {
                "courseid": courseid,
                "section": section,
                "name": name,
                "intro": intro,
                "chaptertitle": chaptertitle,
                "chaptercontent": chaptercontent,
                "visible": visible,
            },
        )

    @mcp.tool()
    async def create_label(
        courseid: int,
        content: str,
        section: int = 0,
        name: str = "",
        visible: int = 1,
    ) -> dict:
        """⚠️ WRITES LIVE DATA. Create a Label (inline text/HTML) in a course.

        Requires the local_mcpbridge plugin. Returns the new cmid and instance id.
        """
        return await _call(
            "local_mcpbridge_create_label",
            {
                "courseid": courseid,
                "section": section,
                "content": content,
                "name": name,
                "visible": visible,
            },
        )

    @mcp.tool()
    async def create_url(
        courseid: int,
        name: str,
        externalurl: str,
        section: int = 0,
        intro: str = "",
        display: int = 0,
        visible: int = 1,
    ) -> dict:
        """⚠️ WRITES LIVE DATA. Create a URL resource in a course.

        Requires the local_mcpbridge plugin. Returns the new cmid and instance id.
        """
        return await _call(
            "local_mcpbridge_create_url",
            {
                "courseid": courseid,
                "section": section,
                "name": name,
                "externalurl": externalurl,
                "intro": intro,
                "display": display,
                "visible": visible,
            },
        )

    @mcp.tool()
    async def create_quiz(
        courseid: int,
        name: str,
        section: int = 0,
        intro: str = "",
        timeopen: int = 0,
        timeclose: int = 0,
        timelimit: int = 0,
        grade: float = 100.0,
        attempts: int = 0,
        visible: int = 1,
    ) -> dict:
        """⚠️ WRITES LIVE DATA. Create an empty Quiz activity (settings only).

        Requires the local_mcpbridge plugin. This creates the quiz container;
        adding questions is a separate, heavier operation (not supported here).
        Returns the new cmid and quiz instance id.
        """
        return await _call(
            "local_mcpbridge_create_quiz",
            {
                "courseid": courseid,
                "section": section,
                "name": name,
                "intro": intro,
                "timeopen": timeopen,
                "timeclose": timeclose,
                "timelimit": timelimit,
                "grade": grade,
                "attempts": attempts,
                "visible": visible,
            },
        )

    @mcp.tool()
    async def add_quiz_question(
        quizcmid: int,
        name: str,
        questiontext: str,
        answers: list[dict],
        single: int = 1,
        defaultmark: float = 1.0,
    ) -> dict:
        """⚠️ WRITES LIVE DATA. Add a multiple-choice question to an existing quiz.

        Requires the local_mcpbridge plugin. `quizcmid` is the quiz's course
        module id (the cmid returned by create_quiz). `answers` is a list of
        dicts, each: {"text": "...", "fraction": 1.0, "feedback": ""} where
        fraction is the grade weight (1.0 = fully correct, 0 = wrong, negatives
        penalise). Provide at least two answers. `single=1` means exactly one
        correct answer. Returns the new question id and its slot in the quiz.
        """
        return await _call(
            "local_mcpbridge_add_quiz_question",
            {
                "quizcmid": quizcmid,
                "name": name,
                "questiontext": questiontext,
                "answers": answers,
                "single": single,
                "defaultmark": defaultmark,
            },
        )


if ALLOW_WRITE:
    _register_write_tools()


def main() -> None:
    """Entry point for the stdio MCP server."""
    mcp.run()


if __name__ == "__main__":
    main()
