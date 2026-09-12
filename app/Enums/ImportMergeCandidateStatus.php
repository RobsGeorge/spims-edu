<?php

namespace App\Enums;

/**
 * The identity review queue's lifecycle — rung 6 of the matching ladder. See
 * docs/legacy-data-import-plan.md §6 and §11.10.
 *
 * `Rejected` is reserved by the schema in §4.1 for a future "different person, do
 * nothing" outcome. The L2 merge-queue screen only wires up three actions — merge,
 * reject-and-create-new, skip — and "reject and create new" lands on `NewUser`
 * (a user actually gets created), not `Rejected`.
 */
enum ImportMergeCandidateStatus: string
{
    case Pending = 'PENDING';
    case Merged = 'MERGED';
    case Rejected = 'REJECTED';
    case NewUser = 'NEW_USER';
}
