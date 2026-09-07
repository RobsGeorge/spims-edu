# Step A2-a — Shell Migration (layouts, partials, hubs)

Step ID: `A2-a`  
Wave: W2a — **runs alone and merges BEFORE W2b starts**  
Scope: resources/views/layouts, resources/views/partials, resources/views/hubs,
       resources/views/roles-hub, resources/views/dashboard.blade.php, resources/views/home.blade.php  
**OWNS ALL SHARED SHELL FILES. Every other A2 lane renders inside these files.**

## Why this lane is special
The nav, sidebar, and main layout wrap every authenticated view. Getting this wrong breaks every
other lane. It merges first so the remaining eight lanes rebase onto a stable shell.

## Scope (17 files)
All files under: `layouts/`, `partials/`, `hubs/`, `roles-hub/`  
Plus root: `dashboard.blade.php`, `home.blade.php`

## What to do (per file)
Apply the migration table from `docs/design-system.md`:
1. Replace `card border-0 shadow-sm` with `<x-card variant="...">` (choose the right tier)
2. Replace bare `<h1>` with `<x-page-header>`
3. Replace `text-muted`, `bg-light`, `bg-secondary` with token classes
4. Replace raw enum `->value` with `<x-badge>` or a lang key
5. Replace raw `*_minor` with `<x-money>`
6. Mobile-first grid: every `col-md-*` must also have an unprefixed or `col-sm-*` sibling
7. Add icons from the vocabulary where missing (nav items, primary actions)
8. Verify light + dark + RTL at 360/768/1440
9. Add missing lang keys to ALL THREE locales (ar, en, fr)

## Rules for this lane
- Do NOT touch routes, controllers, or migrations (scope-escape check will fail you)
- Do NOT change the data passed to views — only presentation
- Note IA problems in the PR but do not fix them
- The nav items need icons from the vocabulary — one per item

## Done
`echo "A2-a" > .claude/current-step` then `./scripts/validate-step.sh A2-a`

After passing: commit, push `worktree-A2-a`, notify that W2b can now rebase.
