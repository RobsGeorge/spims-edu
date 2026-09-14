# Learn Hub — Use Cases

Dry-run validated against codebase state as of 2026-09-14.

---

## UC-L1 · New Student Enrolls and Views Course Player

**Actor**: Student (new, no prior activity)
**Preconditions**: Student enrolled in an offering with 3 weeks defined.

| Step | Action | Expected | Validated |
|------|--------|----------|-----------|
| 1 | Navigate to `/learn/{offering}` | 200 OK; course title shown | ✓ |
| 2 | Page renders week accordion | All 3 weeks shown as accordion panels | ✓ `offering_view_shows_all_published_weeks_in_accordion` |
| 3 | Week 1 has items | Item titles appear inside panel | ✓ `offering_view_shows_item_titles_for_active_week` |
| 4 | Week 1 link is present | `route('learn.week', [$offering, $week])` href exists | ✓ `offering_view_shows_open_week_link_for_unlocked_week` |
| 5 | Weeks 2 and 3 without `unlock_date` | Visible and accessible (not locked) | ✓ (cohort mode: no date = always open) |

---

## UC-L2 · Student Completes an Item

**Actor**: Enrolled student
**Preconditions**: Week with one item exists; item not yet completed.

| Step | Action | Expected | Validated |
|------|--------|----------|-----------|
| 1 | POST `/learn/{offering}/items/{item}/complete` | Redirect to item page | ✓ `item_can_be_marked_complete_via_post` |
| 2 | Revisit week page | Item shows `learn.completed` badge | ✓ `week_view_shows_items_with_completion_badges` |

---

## UC-L3 · Student Encounters Locked Week

**Actor**: Enrolled student
**Preconditions**: Offering with week 2 having `unlock_date` in the future.

| Step | Action | Expected | Validated |
|------|--------|----------|-----------|
| 1 | Navigate to `/learn/{offering}` | Week 2 panel visible but marked locked | ✓ `offering_view_shows_locked_state_for_future_unlock_week` |
| 2 | Week 2 panel | Shows `learn.locked` label, no open-week link | ✓ |
| 3 | Week 1 panel (no unlock_date) | Shows open-week link normally | ✓ |

---

## UC-L4 · Returning Student Sees Progress

**Actor**: Student with partial completion history
**Preconditions**: 2-item week; student has completed item 1 only.

| Step | Action | Expected | Validated |
|------|--------|----------|-----------|
| 1 | Navigate to week page | Both items listed | ✓ |
| 2 | Item 1 | Shows `learn.completed` badge | ✓ `week_view_shows_items_with_completion_badges` |
| 3 | Item 2 | No completed badge | ✓ |

---

## Known Gaps / Watch Items

| # | Gap | Status |
|---|-----|--------|
| G-L1 | Self-paced gating (completion-dependent unlock) not yet tested | Deferred — ContentGatingService covers it; integration test pending |
| G-L2 | Item player views (video/reading/quiz) not tested here | Covered by existing item-type suites |
| G-L3 | Student who is not enrolled hitting `/learn/{offering}` | Expect 403; enforced by `AuthorizeService`; not added to this suite |
