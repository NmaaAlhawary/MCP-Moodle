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

import asyncio
import html as _htmllib
import os
import re
import time as _time
from datetime import datetime, timezone
from typing import Any

import httpx
from mcp.server.fastmcp import FastMCP

# --------------------------------------------------------------------------- #
# Configuration (env vars only — never hard-code or log the token).
# Load a .env sitting next to this file so the server works regardless of the
# working directory it is launched from (e.g. by an MCP client). Real env vars
# already set take precedence over the file.
# --------------------------------------------------------------------------- #

try:
    from dotenv import load_dotenv

    load_dotenv(os.path.join(os.path.dirname(os.path.abspath(__file__)), ".env"))
except ImportError:
    pass

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
# STUDENT / ANALYSIS READ TOOLS (always registered).
#
# These build on the raw web service functions above to give an AI assistant a
# student's-eye view: courses, assignments, deadlines, grades, progress, and
# combined dashboards. The "analysis" tools gather and structure the relevant
# Moodle data; the calling AI does the natural-language reasoning over it.
#
# They rely on extra web service functions being present in the token's service:
#   core_enrol_get_users_courses, mod_assign_get_assignments,
#   mod_assign_get_submission_status, gradereport_overview_get_course_grades,
#   core_calendar_get_action_events_by_timesort, mod_forum_get_forums_by_courses,
#   mod_forum_get_forum_discussions, core_completion_get_activities_completion_status
# --------------------------------------------------------------------------- #

_STOPWORDS = {
    "the", "and", "for", "you", "your", "this", "that", "with", "from", "have",
    "will", "are", "was", "not", "but", "all", "can", "our", "out", "use", "any",
    "how", "who", "why", "what", "when", "which", "into", "than", "then", "them",
    "should", "must", "may", "shall", "each", "also", "using", "used", "one", "two",
    "assignment", "assignments", "task", "please", "student", "students", "submit",
}


def _now() -> int:
    return int(_time.time())


def _iso(ts: int | None) -> str | None:
    if not ts:
        return None
    return datetime.fromtimestamp(ts, timezone.utc).isoformat()


def _week_label(ts: int) -> str:
    iso = datetime.fromtimestamp(ts, timezone.utc).isocalendar()
    return f"{iso[0]}-W{iso[1]:02d}"


def _strip_html(text: str | None) -> str:
    if not text:
        return ""
    text = re.sub(r"<[^>]+>", " ", text)
    text = _htmllib.unescape(text)
    return re.sub(r"\s+", " ", text).strip()


def _keywords(text: str, limit: int = 12) -> list[str]:
    words = re.findall(r"[a-zA-Z][a-zA-Z0-9_-]{2,}", (text or "").lower())
    result: list[str] = []
    for w in words:
        if w not in _STOPWORDS and w not in result:
            result.append(w)
    return result[:limit]


_USERID_CACHE: dict[str, int] = {}


async def _get_userid() -> int:
    if "id" not in _USERID_CACHE:
        info = await _call("core_webservice_get_site_info")
        _USERID_CACHE["id"] = int(info.get("userid", 0))
    return _USERID_CACHE["id"]


async def _my_courses_raw() -> list:
    uid = await _get_userid()
    return await _call("core_enrol_get_users_courses", {"userid": uid}) or []


async def _assignments_raw(courseids: list[int] | None = None) -> list:
    if not courseids:
        courseids = [c["id"] for c in await _my_courses_raw()]
    data = await _call("mod_assign_get_assignments", {"courseids": courseids})
    out = []
    for c in data.get("courses", []):
        for a in c.get("assignments", []):
            a["coursename"] = c.get("fullname") or c.get("shortname")
            out.append(a)
    return out


def _is_submitted(status: dict) -> bool:
    last = (status or {}).get("lastattempt") or {}
    sub = last.get("submission") or {}
    return sub.get("status") == "submitted"


def _grading_status(status: dict) -> str | None:
    last = (status or {}).get("lastattempt") or {}
    return last.get("gradingstatus")


async def _enriched_assignments(courseids: list[int] | None = None) -> list:
    """All assignments that have a due date, enriched with submission status."""
    uid = await _get_userid()
    assigns = [a for a in await _assignments_raw(courseids) if a.get("duedate")]

    async def enrich(a: dict) -> dict:
        submitted, grading = False, None
        try:
            st = await _call(
                "mod_assign_get_submission_status",
                {"assignid": a["id"], "userid": uid},
            )
            submitted = _is_submitted(st)
            grading = _grading_status(st)
        except MoodleError:
            pass  # Some assignments do not expose status; treat as unknown.
        due = a.get("duedate") or 0
        return {
            "assignid": a["id"],
            "cmid": a.get("cmid"),
            "name": a.get("name"),
            "courseid": a.get("course"),
            "coursename": a.get("coursename"),
            "duedate": due,
            "due_iso": _iso(due),
            "days_left": round((due - _now()) / 86400, 1) if due else None,
            "submitted": submitted,
            "gradingstatus": grading,
        }

    return list(await asyncio.gather(*[enrich(a) for a in assigns]))


async def _course_materials(courseid: int) -> list:
    contents = await _call("core_course_get_contents", {"courseid": courseid})
    mats = []
    for sec in contents or []:
        for m in sec.get("modules", []):
            mats.append({
                "courseid": courseid,
                "section": sec.get("name"),
                "cmid": m.get("id"),
                "name": m.get("name"),
                "modname": m.get("modname"),
                "url": m.get("url"),
                "description": _strip_html(m.get("description", "")),
            })
    return mats


async def _course_progress(courseid: int, uid: int) -> dict:
    try:
        data = await _call(
            "core_completion_get_activities_completion_status",
            {"courseid": courseid, "userid": uid},
        )
        statuses = data.get("statuses", [])
        total = len(statuses)
        done = sum(1 for s in statuses if s.get("state") == 1)
        return {
            "courseid": courseid,
            "activities_total": total,
            "activities_completed": done,
            "percent": round(100 * done / total, 1) if total else None,
        }
    except MoodleError:
        return {"courseid": courseid, "percent": None, "note": "completion tracking not available"}


async def _find_assignment(assignid: int) -> dict | None:
    for a in await _assignments_raw():
        if a["id"] == assignid:
            return a
    return None


# --- Course & content ------------------------------------------------------ #

@mcp.tool()
async def get_my_courses() -> list:
    """Get all courses the current user (the token's user) is enrolled in.

    Read-only. Returns id, fullname, shortname, and progress where available.
    """
    courses = await _my_courses_raw()
    return [
        {
            "id": c.get("id"),
            "fullname": c.get("fullname"),
            "shortname": c.get("shortname"),
            "progress": c.get("progress"),
            "startdate": _iso(c.get("startdate")),
            "enddate": _iso(c.get("enddate")),
        }
        for c in courses
    ]


@mcp.tool()
async def search_course_materials(query: str, courseid: int = 0) -> list:
    """Search across course materials by a text query.

    Read-only. Matches the query (case-insensitive) against activity names,
    descriptions, and section names. Searches one course if courseid is given,
    otherwise all of the user's courses. Returns matching materials.
    """
    q = query.lower().strip()
    course_ids = [courseid] if courseid else [c["id"] for c in await _my_courses_raw()]
    lists = await asyncio.gather(*[_course_materials(cid) for cid in course_ids])
    hits = []
    for mats in lists:
        for m in mats:
            haystack = f"{m['name']} {m['description']} {m['section'] or ''}".lower()
            if q in haystack:
                hits.append(m)
    return hits


@mcp.tool()
async def get_course_announcements(courseid: int = 0) -> list:
    """Get announcements from course news forums.

    Read-only. Reads each course's Announcements (news) forum. Filtered to one
    course if courseid is given, otherwise all of the user's courses. Returns
    the most recent discussions (subject, message, author, time).
    """
    course_ids = [courseid] if courseid else [c["id"] for c in await _my_courses_raw()]
    forums = await _call("mod_forum_get_forums_by_courses", {"courseids": course_ids})
    news = [f for f in (forums or []) if f.get("type") == "news"]
    out = []
    for f in news:
        try:
            disc = await _call("mod_forum_get_forum_discussions", {"forumid": f["id"]})
        except MoodleError:
            continue
        for d in disc.get("discussions", []):
            out.append({
                "courseid": f.get("course"),
                "subject": d.get("subject"),
                "message": _strip_html(d.get("message", "")),
                "author": d.get("userfullname"),
                "modified": _iso(d.get("timemodified")),
                "timemodified": d.get("timemodified"),
            })
    out.sort(key=lambda x: x.get("timemodified") or 0, reverse=True)
    return out


@mcp.tool()
async def get_recent_activity(since: int = 0) -> list:
    """Get recent activity across courses since a given time.

    Read-only. `since` is a unix timestamp; defaults to the last 7 days.
    Aggregates recent announcements and recently updated assignments.
    """
    if not since:
        since = _now() - 7 * 86400
    events: list[dict] = []
    for a in await _assignments_raw():
        tm = a.get("timemodified") or 0
        if tm >= since:
            events.append({
                "type": "assignment",
                "title": a.get("name"),
                "courseid": a.get("course"),
                "coursename": a.get("coursename"),
                "time": _iso(tm),
                "timestamp": tm,
            })
    for ann in await get_course_announcements():
        if (ann.get("timemodified") or 0) >= since:
            events.append({
                "type": "announcement",
                "title": ann.get("subject"),
                "courseid": ann.get("courseid"),
                "time": ann.get("modified"),
                "timestamp": ann.get("timemodified"),
            })
    events.sort(key=lambda x: x.get("timestamp") or 0, reverse=True)
    return events


# --- Assignments & deadlines ----------------------------------------------- #

@mcp.tool()
async def get_assignments(courseids: list[int] | None = None) -> list:
    """Get assignments for courses.

    Read-only. Pass a list of course IDs, or omit to use all enrolled courses.
    Returns id, cmid, name, course, due date, and open/cutoff dates.
    """
    return [
        {
            "assignid": a.get("id"),
            "cmid": a.get("cmid"),
            "name": a.get("name"),
            "courseid": a.get("course"),
            "coursename": a.get("coursename"),
            "duedate": a.get("duedate"),
            "due_iso": _iso(a.get("duedate")),
            "allowsubmissionsfromdate": _iso(a.get("allowsubmissionsfromdate")),
            "cutoffdate": _iso(a.get("cutoffdate")),
        }
        for a in await _assignments_raw(courseids)
    ]


@mcp.tool()
async def get_assignment_status(assignid: int) -> dict:
    """Get submission and grading status for a specific assignment.

    Read-only. Returns whether the current user has submitted, the grading
    status, and the grade if one has been released.
    """
    uid = await _get_userid()
    st = await _call("mod_assign_get_submission_status", {"assignid": assignid, "userid": uid})
    last = st.get("lastattempt") or {}
    feedback = st.get("feedback") or {}
    return {
        "assignid": assignid,
        "submitted": _is_submitted(st),
        "gradingstatus": _grading_status(st),
        "grade": (feedback.get("grade") or {}).get("grade"),
        "cansubmit": last.get("cansubmit"),
        "graded": bool(feedback.get("grade")),
    }


@mcp.tool()
async def get_upcoming_deadlines(within_days: int = 0) -> list:
    """Get upcoming assignment deadlines across all courses, soonest first.

    Read-only. If within_days > 0, only deadlines within that many days are
    returned. Each item includes days_left and whether it is already submitted.
    """
    now = _now()
    tasks = [t for t in await _enriched_assignments() if t["duedate"] >= now]
    if within_days:
        cutoff = now + within_days * 86400
        tasks = [t for t in tasks if t["duedate"] <= cutoff]
    tasks.sort(key=lambda t: t["duedate"])
    return tasks


@mcp.tool()
async def get_overdue_assignments() -> list:
    """Get unsubmitted assignments past their due date, most overdue first.

    Read-only. Only assignments with a due date in the past that the user has
    not submitted are returned.
    """
    now = _now()
    tasks = [
        t for t in await _enriched_assignments()
        if 0 < t["duedate"] < now and not t["submitted"]
    ]
    tasks.sort(key=lambda t: t["duedate"])
    return tasks


@mcp.tool()
async def get_actionable_tasks() -> list:
    """Get a prioritized list of tasks needing action, most urgent first.

    Read-only. Includes any unsubmitted assignment with a due date. Each task is
    tagged with an urgency level (overdue / due_soon / upcoming).
    """
    tasks = [t for t in await _enriched_assignments() if t["duedate"] and not t["submitted"]]
    for t in tasks:
        days = t["days_left"]
        if days is not None and days < 0:
            t["urgency"] = "overdue"
        elif days is not None and days <= 3:
            t["urgency"] = "due_soon"
        else:
            t["urgency"] = "upcoming"
    tasks.sort(key=lambda t: t["duedate"])
    return tasks


@mcp.tool()
async def analyze_assignment(assignid: int) -> dict:
    """Analyze an assignment: status, requirements, materials, progress, deadline.

    Read-only. Gathers the assignment's description, submission/grading status,
    deadline math, and the course materials most relevant to it, so the AI can
    reason about what to do next. Does not itself write anything.
    """
    a = await _find_assignment(assignid)
    if not a:
        raise MoodleError(f"Assignment {assignid} not found in the user's courses.")
    uid = await _get_userid()
    submitted, grading = False, None
    try:
        st = await _call("mod_assign_get_submission_status", {"assignid": assignid, "userid": uid})
        submitted, grading = _is_submitted(st), _grading_status(st)
    except MoodleError:
        pass
    due = a.get("duedate") or 0
    return {
        "assignid": assignid,
        "name": a.get("name"),
        "courseid": a.get("course"),
        "coursename": a.get("coursename"),
        "description": _strip_html(a.get("intro", "")),
        "attachments": [f.get("filename") for f in a.get("introattachments", [])],
        "duedate": due,
        "due_iso": _iso(due),
        "days_left": round((due - _now()) / 86400, 1) if due else None,
        "submitted": submitted,
        "gradingstatus": grading,
        "relevant_materials": await find_relevant_materials(assignid),
    }


@mcp.tool()
async def extract_assignment_requirements(assignid: int) -> dict:
    """Extract the source text of an assignment for requirement analysis.

    Read-only. Returns the assignment's full description text and attachments so
    the calling AI can identify requirements, deliverables, constraints, and
    evaluation criteria. The `sentences` list is a convenience splitting of the
    description; the AI should interpret them into structured requirements.
    """
    a = await _find_assignment(assignid)
    if not a:
        raise MoodleError(f"Assignment {assignid} not found in the user's courses.")
    text = _strip_html(a.get("intro", ""))
    sentences = [s.strip() for s in re.split(r"(?<=[.!?])\s+", text) if s.strip()]
    return {
        "assignid": assignid,
        "name": a.get("name"),
        "description": text,
        "sentences": sentences,
        "attachments": [f.get("filename") for f in a.get("introattachments", [])],
    }


@mcp.tool()
async def find_relevant_materials(assignid: int) -> list:
    """Find course materials relevant to an assignment, ranked by relevance.

    Read-only. Pulls keywords from the assignment title and description, then
    scores the materials in the same course by keyword overlap. Returns the
    matches sorted by score (most relevant first).
    """
    a = await _find_assignment(assignid)
    if not a:
        raise MoodleError(f"Assignment {assignid} not found in the user's courses.")
    keywords = _keywords(f"{a.get('name', '')} {_strip_html(a.get('intro', ''))}")
    mats = await _course_materials(a.get("course"))
    scored = []
    for m in mats:
        blob = f"{m['name']} {m['description']}".lower()
        score = sum(1 for kw in keywords if kw in blob)
        if score:
            scored.append({**m, "score": score})
    scored.sort(key=lambda m: m["score"], reverse=True)
    return scored


@mcp.tool()
async def decompose_task(assignid: int) -> dict:
    """Break an assignment into subtasks with effort, dependencies, timeline.

    Read-only. Returns the assignment context plus the number of days available
    before the deadline, as a scaffold. The calling AI should populate the
    subtasks, effort estimates, dependencies, and critical path from this data.
    """
    ctx = await analyze_assignment(assignid)
    days = ctx.get("days_left")
    return {
        "assignment": ctx,
        "days_available": days,
        "guidance": (
            "Break this assignment into ordered subtasks. For each, estimate "
            "effort (hours), list dependencies, and flag those on the critical "
            "path. Fit the total effort within days_available before the deadline."
        ),
        "subtasks": [],  # To be filled in by the AI from the context above.
    }


@mcp.tool()
async def create_implementation_plan(assignid: int) -> dict:
    """Build a step-by-step plan with timeline, milestones, and risks.

    Read-only. Returns the assignment context and four evenly spaced milestone
    dates between now and the deadline, as a scaffold for the AI to turn into a
    concrete plan (steps, resources, milestones, risks).
    """
    ctx = await analyze_assignment(assignid)
    now = _now()
    due = ctx.get("duedate") or 0
    milestones = []
    if due > now:
        span = due - now
        for i in range(1, 5):
            milestones.append({"milestone": i, "target_date": _iso(now + span * i // 4)})
    return {
        "assignment": ctx,
        "suggested_milestones": milestones,
        "guidance": (
            "Produce a step-by-step plan: for each step give a title, the "
            "resources/materials needed (use relevant_materials), the milestone "
            "it maps to, and any risks. Keep it within the suggested_milestones."
        ),
        "steps": [],  # To be filled in by the AI.
    }


# --- Grades & progress ----------------------------------------------------- #

@mcp.tool()
async def get_grades(courseid: int = 0) -> Any:
    """Get a grade overview for all courses, or detailed grades for one course.

    Read-only. With no courseid, returns the cross-course grade overview. With a
    courseid, returns the detailed grade items for that course.
    """
    if courseid:
        uid = await _get_userid()
        return await _call("gradereport_user_get_grade_items", {"courseid": courseid, "userid": uid})
    return await _call("gradereport_overview_get_course_grades")


@mcp.tool()
async def get_course_progress(courseid: int = 0) -> Any:
    """Get progress/completion for one course or all courses.

    Read-only. Requires completion tracking to be enabled on the course(s).
    Returns activities completed vs total and a percentage.
    """
    uid = await _get_userid()
    if courseid:
        return await _course_progress(courseid, uid)
    course_ids = [c["id"] for c in await _my_courses_raw()]
    return list(await asyncio.gather(*[_course_progress(cid, uid) for cid in course_ids]))


@mcp.tool()
async def get_course_health(courseid: int) -> dict:
    """Health check for a course: progress, grade, unsubmitted and overdue counts.

    Read-only. Combines completion, the overview grade, and assignment status
    into a single snapshot for one course.
    """
    now = _now()
    uid = await _get_userid()
    prog = await _course_progress(courseid, uid)
    enriched = await _enriched_assignments([courseid])
    unsubmitted = [t for t in enriched if not t["submitted"]]
    overdue = [t for t in enriched if 0 < t["duedate"] < now and not t["submitted"]]
    grade = None
    try:
        overview = await _call("gradereport_overview_get_course_grades")
        row = next((g for g in overview.get("grades", []) if g.get("courseid") == courseid), None)
        grade = row.get("grade") if row else None
    except MoodleError:
        pass
    return {
        "courseid": courseid,
        "percent_complete": prog.get("percent"),
        "grade": grade,
        "unsubmitted_count": len(unsubmitted),
        "overdue_count": len(overdue),
    }


@mcp.tool()
async def get_study_load() -> list:
    """Analyze assignment distribution by week to spot heavy weeks.

    Read-only. Buckets upcoming assignments by ISO week and counts them, so the
    AI can flag weeks with an unusually heavy workload.
    """
    buckets: dict[str, dict] = {}
    for t in await _enriched_assignments():
        if not t["duedate"]:
            continue
        label = _week_label(t["duedate"])
        b = buckets.setdefault(label, {"week": label, "count": 0, "assignments": []})
        b["count"] += 1
        b["assignments"].append(t["name"])
    return sorted(buckets.values(), key=lambda b: b["week"])


# --- Aggregated overviews -------------------------------------------------- #

@mcp.tool()
async def get_upcoming_events(limit: int = 20) -> list:
    """Get upcoming calendar events from Moodle, soonest first.

    Read-only. Uses Moodle's action-events calendar view from now onward.
    """
    data = await _call(
        "core_calendar_get_action_events_by_timesort",
        {"timesortfrom": _now(), "limitnum": limit},
    )
    events = data.get("events", []) if isinstance(data, dict) else []
    return [
        {
            "name": e.get("name"),
            "timestart": _iso(e.get("timestart")),
            "timesort": e.get("timesort"),
            "courseid": (e.get("course") or {}).get("id"),
            "url": (e.get("action") or {}).get("url") or e.get("url"),
        }
        for e in events
    ]


@mcp.tool()
async def semester_dashboard() -> dict:
    """Combined overview of courses, upcoming deadlines, and grades.

    Read-only. A one-call snapshot for a "how is my semester going?" question.
    """
    courses, deadlines, grades = await asyncio.gather(
        get_my_courses(),
        get_upcoming_deadlines(),
        get_grades(),
    )
    return {
        "courses": courses,
        "upcoming_deadlines": deadlines[:10],
        "grades": grades,
    }


@mcp.tool()
async def daily_briefing() -> dict:
    """Daily summary: overdue count, today's deadlines, recent grades, events, tasks.

    Read-only. A compact "what do I need to know today?" digest.
    """
    now = _now()
    overdue, tasks, events, grades = await asyncio.gather(
        get_overdue_assignments(),
        get_actionable_tasks(),
        get_upcoming_events(5),
        get_grades(),
    )
    todays = [t for t in tasks if t["days_left"] is not None and 0 <= t["days_left"] < 1]
    return {
        "date": _iso(now),
        "overdue_count": len(overdue),
        "todays_deadlines": todays,
        "recent_grades": grades,
        "upcoming_events": events,
        "top_tasks": tasks[:5],
    }


@mcp.tool()
async def weekly_review() -> dict:
    """Weekly summary: submitted/graded counts, deadlines, overdue count, progress.

    Read-only. A "how did this week go and what's next?" digest.
    """
    enriched = await _enriched_assignments()
    now = _now()
    submitted = [t for t in enriched if t["submitted"]]
    graded = [t for t in enriched if t["gradingstatus"] == "graded"]
    overdue = [t for t in enriched if 0 < t["duedate"] < now and not t["submitted"]]
    this_week = [t for t in enriched if t["days_left"] is not None and 0 <= t["days_left"] <= 7]
    progress = await get_course_progress()
    percents = [p.get("percent") for p in progress if p.get("percent") is not None]
    avg = round(sum(percents) / len(percents), 1) if percents else None
    return {
        "submitted_count": len(submitted),
        "graded_count": len(graded),
        "deadlines_this_week": sorted(this_week, key=lambda t: t["duedate"]),
        "overdue_count": len(overdue),
        "average_progress_percent": avg,
    }


@mcp.tool()
async def ask_moodle(question: str) -> dict:
    """Ask a natural-language question and have it routed to the right data.

    Read-only. Picks the most relevant data source based on the question's
    wording and returns that data under `data`, tagged with `routed_to`. The
    calling AI then answers the question from the returned data.
    """
    q = question.lower()
    if any(w in q for w in ("overdue", "late", "missed", "missing")):
        source, data = "get_overdue_assignments", await get_overdue_assignments()
    elif any(w in q for w in ("deadline", "due", "upcoming assignment", "soon")):
        source, data = "get_upcoming_deadlines", await get_upcoming_deadlines()
    elif any(w in q for w in ("grade", "mark", "score", "result")):
        source, data = "get_grades", await get_grades()
    elif any(w in q for w in ("progress", "complete", "completion")):
        source, data = "get_course_progress", await get_course_progress()
    elif any(w in q for w in ("announce", "news")):
        source, data = "get_course_announcements", await get_course_announcements()
    elif any(w in q for w in ("event", "calendar")):
        source, data = "get_upcoming_events", await get_upcoming_events()
    elif any(w in q for w in ("task", "todo", "to-do", "action", "do next")):
        source, data = "get_actionable_tasks", await get_actionable_tasks()
    elif any(w in q for w in ("course", "class", "enrol", "enroll")):
        source, data = "get_my_courses", await get_my_courses()
    else:
        source, data = "daily_briefing", await daily_briefing()
    return {"question": question, "routed_to": source, "data": data}


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

    @mcp.tool()
    async def create_forum(
        courseid: int,
        name: str,
        section: int = 0,
        intro: str = "",
        type: str = "general",
        visible: int = 1,
    ) -> dict:
        """⚠️ WRITES LIVE DATA. Create a Forum activity in a course.

        Requires the local_mcpbridge plugin. `type` is one of: general,
        eachuser, single, qanda, blog. Returns the new cmid and instance id.
        """
        return await _call(
            "local_mcpbridge_create_forum",
            {
                "courseid": courseid,
                "section": section,
                "name": name,
                "intro": intro,
                "type": type,
                "visible": visible,
            },
        )

    @mcp.tool()
    async def create_choice(
        courseid: int,
        name: str,
        question: str,
        options: list[str],
        section: int = 0,
        allowupdate: int = 1,
        visible: int = 1,
    ) -> dict:
        """⚠️ WRITES LIVE DATA. Create a Choice (poll) activity in a course.

        Requires the local_mcpbridge plugin. `options` is a list of at least two
        answer strings students pick from. Returns the new cmid and instance id.
        """
        return await _call(
            "local_mcpbridge_create_choice",
            {
                "courseid": courseid,
                "section": section,
                "name": name,
                "question": question,
                "options": options,
                "allowupdate": allowupdate,
                "visible": visible,
            },
        )

    @mcp.tool()
    async def create_assignment(
        courseid: int,
        name: str,
        section: int = 0,
        intro: str = "",
        duedate: int = 0,
        grade: int = 100,
        visible: int = 1,
    ) -> dict:
        """⚠️ WRITES LIVE DATA. Create an Assignment activity in a course.

        Requires the local_mcpbridge plugin. Enables online-text and file
        submissions with feedback comments. `duedate` is a unix timestamp
        (0 = no due date). Returns the new cmid and instance id.
        """
        return await _call(
            "local_mcpbridge_create_assignment",
            {
                "courseid": courseid,
                "section": section,
                "name": name,
                "intro": intro,
                "duedate": duedate,
                "grade": grade,
                "visible": visible,
            },
        )


if ALLOW_WRITE:
    _register_write_tools()


def main() -> None:
    """Entry point for the stdio MCP server."""
    mcp.run()


if __name__ == "__main__":
    main()
