<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\LedgerDirection;
use App\Enums\LedgerReason;
use App\Enums\WalletKind;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'wallet_id',
        'currency',
        'kind',
        'direction',
        'amount_minor',
        'reason',
        'related_payment_id',
        'related_invoice_id',
        'note',
        'created_by_id',
    ];

    protected $casts = [
        'currency' => Currency::class,
        'kind' => WalletKind::class,
        'direction' => LedgerDirection::class,
        'reason' => LedgerReason::class,
        'amount_minor' => 'integer',
        'created_at' => 'datetime',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(WalletAccount::class, 'wallet_id');
    }
}
