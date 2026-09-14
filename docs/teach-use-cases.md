# Teach Hub — Use Cases

Dry-run validated against codebase state as of 2026-09-14.

---

## UC-T1 · Instructor Accesses Teach Hub

**Actor**: Staff user assigned as Instructor on an offering
**Preconditions**: `OfferingStaff` row exists linking instructor to offering.

| Step | Action | Expected | Validated |
|------|--------|----------|-----------|
| 1 | Navigate to `/teach` | 200 OK; offering listed by course code | ✓ `teach_index_is_accessible_to_instructor` |
| 2 | Offering card shows enrollment count | `stat_enrolled` count is shown | ✓ `teach_index_shows_enrollment_count` |

---

## UC-T2 · Instructor Views Content Tab

**Actor**: Instructor on an offering with weeks and enrolled students
**Preconditions**: At least 1 enrolled student, 1 week with 1 item.

| Step | Action | Expected | Validated |
|------|--------|----------|-----------|
| 1 | Navigate to `/teach/{offering}` (default tab = content) | 200 OK; content panel rendered | ✓ `teach_show_default_tab_renders_content_panel` |
| 2 | Stat grid appears | `stat_enrolled`, `stat_weeks`, `stat_items` labels visible | ✓ `teach_show_content_tab_shows_stat_grid` |
| 3 | Week accordion shown | Week title and item titles visible | ✓ `teach_show_content_tab_shows_week_accordion` |

---

## UC-T3 · Instructor Views Roster Tab

**Actor**: Instructor
**Preconditions**: 2 students enrolled in the offering.

| Step | Action | Expected | Validated |
|------|--------|----------|-----------|
| 1 | Navigate to `/teach/{offering}?tab=roster` | 200 OK | ✓ |
| 2 | Student names shown | Full name (first + last) appears via `displayName()` | ✓ `teach_show_roster_tab_shows_enrolled_students` |
| 3 | Progress shown | `progress_percent` rendered as `%` value | ✓ `teach_show_roster_tab_shows_progress_percentage` |

---

## UC-T4 · Academic Admin Accesses Teach Hub

**Actor**: User with Academic Admin role (not an Instructor)
**Preconditions**: Academic Admin role assigned.

| Step | Action | Expected | Validated |
|------|--------|----------|-----------|
| 1 | Navigate to `/teach` | 200 OK (admin sees all offerings) | ✓ `academic_admin_can_access_teach_hub` |

---

## UC-T5 · Instructor Navigates to Assessments Tab

**Actor**: Instructor with assessments created
**Preconditions**: At least 1 assessment exists for the offering.

| Step | Action | Expected | Validated |
|------|--------|----------|-----------|
| 1 | Navigate to `/teach/{offering}?tab=assessments` | Assessment titles listed with attempt counts | Manual verification recommended |
| 2 | Create Assessment button present | Routes to `admin.assessments.create` | Structural — not in current test suite |

---

## Known Gaps / Watch Items

| # | Gap | Status |
|---|-----|--------|
| G-T1 | No test for TA (Teaching Assistant) role access | Deferred; TeachAccessService handles it |
| G-T2 | Roster dossier link (`teach.students.show`) not tested end-to-end | Link renders; controller test not in this suite |
| G-T3 | Gradebook lock/unlock not tested via TeachHub | Covered by separate GradebookTest suite |
| G-T4 | Completion tab content not tested | Tab exists; content depends on completion data aggregation |
