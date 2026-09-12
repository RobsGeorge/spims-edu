<?php

/**
 * Legacy data import (Populi + Canvas -> SPIMS) — L5 account-claim invitations.
 * See docs/legacy-data-import-plan.md §9.
 */
return [

    /*
     * Throttling for ImportAccountClaimService::send(): invitations are sent in
     * chunks of this size, with a pause between chunks, so a cohort of a few hundred
     * stale addresses never fires as one tight loop (§9, §15 "thousands of emails
     * fire on commit" risk — this is the mitigation for the separate, explicit send
     * action, not for commit itself, which never sends mail at all).
     */
    'claim_send_chunk_size' => (int) env('IMPORT_CLAIM_SEND_CHUNK_SIZE', 25),

    'claim_send_chunk_delay_ms' => (int) env('IMPORT_CLAIM_SEND_CHUNK_DELAY_MS', 200),

    /*
     * How long a signed claim link stays valid before the student must be re-invited
     * (a fresh link is minted on every send/resend, so this only bounds a single
     * unclaimed link's lifetime).
     */
    'claim_link_ttl_days' => (int) env('IMPORT_CLAIM_LINK_TTL_DAYS', 14),

    /*
     * L8, Part B — AI-assisted mapping (§5.5, §22.3). Off unless explicitly switched
     * on: when false, ImportAiMappingSuggester::fillGaps() returns the deterministic
     * mapping untouched and never calls the AI client at all — not even to no-op.
     * Follows this same file's env-driven convention rather than a `settings` table
     * row, since this feature has no admin settings-management UI to put a toggle in
     * (deliberately out of scope — see §22.3); an operator switches it on via the
     * environment after reading the masking policy shown on the mapping screen.
     */
    'ai_mapping_enabled' => (bool) env('IMPORT_AI_MAPPING_ENABLED', false),

];
