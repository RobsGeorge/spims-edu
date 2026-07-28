<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\WalletKind;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Donation extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'amount_minor',
        'currency',
        'kind',
        'designation',
        'payment_id',
    ];

    protected $casts = [
        'currency' => Currency::class,
        'kind' => WalletKind::class,
        'amount_minor' => 'integer',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
