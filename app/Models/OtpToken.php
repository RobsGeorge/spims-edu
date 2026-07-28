<?php

namespace App\Models;

use App\Enums\OtpPurpose;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OtpToken extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'purpose',
        'code_hash',
        'expires_at',
        'consumed_at',
    ];

    protected $casts = [
        'purpose' => OtpPurpose::class,
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
