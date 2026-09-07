# Step 2 — Landing Rebuild

Step ID: `2`  
Wave: W4 (needs Step 1's description columns) | New UI — design system native.  
Scope: resources/views/home.blade.php (and partials)

## What to build
Rebuild the home page. It is currently generic. Make it specific to this school's world.

Sections:
1. **Hero**: opens with the most characteristic element of this school's identity. Not a gradient
   with a big number. What makes this school distinct should be visible in the first viewport.
2. **Program tier strip**: lists program types (Diploma, Certificate, etc.). Hide empty tiers.
   Test with zero programs in a tier — it must genuinely not render.
3. **Featured courses**: 3–4 cards, price via `<x-money>`, link to `/courses/{code}`
   (pre-wired with `Route::has('courses.show')` guard — no dead links).
4. **How it works**: a 3–4 step process strip (apply → enroll → learn → graduate).
5. **Fees & payments**: brief fee summary and payment options.
6. **Trust strip + credential verifier**: includes a working verify-a-credential widget that
   resolves a real seeded serial and rejects a bogus one.
7. **Footer CTA**: clear call to action linking to `/programs` or `/applications/create`.

Pre-wire all `Route::has()` guards. A section that links to a non-existent route must either
fall back gracefully or not render.

## Gate
- Guest 200
- Empty tiers genuinely don't render (assert with a seeded program-free tier)
- Verify widget resolves a real credential serial and rejects a bogus one
- No `Route::has` guard renders a dead link
- All copy in ar, en, fr

`echo "2" > .claude/current-step` then `./scripts/validate-step.sh 2`
