# Step 4 — Public Course Detail

Step ID: `4`  
Wave: W5 (needs Step 3) | New UI — design system native.  
Scope: routes, resources/views/courses

## What to build
`GET /courses/{code}` → `PublicCourseController::show` (guest-accessible)

### Page content
- Description (`courses.description` added in Step 1)
- Prerequisites: linked list (each links to that course's own `/courses/{code}`)
- Parent programs: list of programs that include this course, showing:
  - Program name (linked to `/programs/{code}`)
  - Year level this course sits in
  - Required vs. elective indicator
- Open offerings: mode, dates, price via `<x-money>`, seats available, enroll CTA
- Instructors: name, avatar
- Auth-aware CTAs:
  - Guest: "Sign in to enroll" prompt
  - Authenticated student: enrollment action or waitlist

## Gate
- Guest 200
- A course with prerequisites: each linked and the linked page resolves
- A course in TWO programs: both listed with correct year levels
- Guest sees a sign-in prompt where an authed student sees an enroll action
- No raw minor units in output

`echo "4" > .claude/current-step` then `./scripts/validate-step.sh 4`
