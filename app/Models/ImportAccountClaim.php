<?php

namespace App\Models;

use App\Enums\ImportAccountClaimStatus;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportAccountClaim extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'batch_id',
        'status',
        'invited_at',
        'claimed_at',
        'last_reminded_at',
        'reminder_count',
    ];

    protected $casts = [
        'status' => ImportAccountClaimStatus::class,
        'invited_at' => 'datetime',
        'claimed_at' => 'datetime',
        'last_reminded_at' => 'datetime',
        'reminder_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'batch_id');
    }
}
