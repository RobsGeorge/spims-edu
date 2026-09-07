# Step A2-c — Learn, Courses, Assessments, Attendance, Live

Step ID: `A2-c`  
Wave: W2b (rebase onto merged A2-a first)  
Scope: resources/views/learn, resources/views/courses, resources/views/assessments,
       resources/views/assignments, resources/views/completion, resources/views/discussions,
       resources/views/attendance, resources/views/live

## Scope (16 files)
`learn/` (+ partials), `courses/`, `assessments/`, `assignments/`, `completion/`,
`discussions/` (+ partials), `attendance/`, `live/`

## What to do
Apply migration table from `docs/design-system.md` to every file in scope:
1. All banned-pattern replacements
2. Mobile-first grid pass
3. Icons from vocabulary (assessment status, attendance states, completion indicators)
4. Localize new strings in ALL THREE locales
5. Verify 360/768/1440 × light/dark/RTL

## Special notes
- Discussion views: thread replies must be readable at 360px — test with long Arabic text
- Assessment status badges: use `<x-badge>` (resolves the lang key internally)
- Live session views: the "Join" action tap target must be ≥44px at 360px

## Done
`echo "A2-c" > .claude/current-step` then `./scripts/validate-step.sh A2-c`
