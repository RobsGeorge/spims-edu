# Add week from Teach Content

Instructors and TAs can add a week from the Teach workspace Content tab, not only from the admin offering editor.

## Shared form

`resources/views/offerings/partials/add-week-form.blade.php` is included from:

- Teach Content tab (`resources/views/teach/show.blade.php`)
- Admin offering show (`resources/views/admin/offerings/show.blade.php`)

Both POST `route('admin.offerings.weeks', $offering)` with `number`, `title`, and optional `unlock_date`. The existing `OfferingController::addWeek` + `OfferingService::addWeek` path stays unchanged (`offerings.content`, own offering; audited as `offerings.add_week`). Copy is `offerings.add_week` / `offerings.week_added`.

`addWeek` returns `back()`, so a submit from Teach lands back on the Content tab.

## Empty offering

The form is shown even when the offering has no weeks. After the first week is created (with no items yet), Teach includes `offerings/partials/week-content-builder`, which loops `$offering->weeks` and shows the new week heading plus the add-item form.

## Authz

Same as other content mutations: staffed instructor/TA succeed; outsider instructor and student receive 403.
