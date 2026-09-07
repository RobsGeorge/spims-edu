# Step A2-h — Admin Applications, Communications, Credentials, Events, Staff Surveys

Step ID: `A2-h`  
Wave: W2b (rebase onto merged A2-a first)  
Scope: resources/views/admin/applications, resources/views/admin/application-forms,
       resources/views/admin/enrollments, resources/views/admin/communications,
       resources/views/admin/credentials, resources/views/admin/certificate-templates,
       resources/views/admin/events, resources/views/staff

## Scope (16 files)

## What to do
Apply migration table from `docs/design-system.md`:
1. All banned-pattern replacements
2. Application status: `<x-badge>` (resolves lang key internally)
3. Credential views: `<x-money>` for any fee amounts
4. Communication views: action buttons follow "icon + text" rule for destructive actions
5. Mobile-first grid pass
6. Localize in ALL THREE locales
7. Verify 360/768/1440 × light/dark/RTL

## Done
`echo "A2-h" > .claude/current-step` then `./scripts/validate-step.sh A2-h`
