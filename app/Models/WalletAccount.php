<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\WalletKind;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WalletAccount extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'egp_money_minor',
        'usd_money_minor',
        'egp_points_minor',
        'usd_points_minor',
    ];

    protected $casts = [
        'egp_money_minor' => 'integer',
        'usd_money_minor' => 'integer',
        'egp_points_minor' => 'integer',
        'usd_points_minor' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'wallet_id');
    }

    public function balance(Currency $currency, WalletKind $kind): int
    {
        return match ([$currency, $kind]) {
            [Currency::Egp, WalletKind::Money] => $this->egp_money_minor,
            [Currency::Usd, WalletKind::Money] => $this->usd_money_minor,
            [Currency::Egp, WalletKind::Points] => $this->egp_points_minor,
            [Currency::Usd, WalletKind::Points] => $this->usd_points_minor,
        };
    }

    public function setBalance(Currency $currency, WalletKind $kind, int $amount): void
    {
        match ([$currency, $kind]) {
            [Currency::Egp, WalletKind::Money] => $this->egp_money_minor = $amount,
            [Currency::Usd, WalletKind::Money] => $this->usd_money_minor = $amount,
            [Currency::Egp, WalletKind::Points] => $this->egp_points_minor = $amount,
            [Currency::Usd, WalletKind::Points] => $this->usd_points_minor = $amount,
        };
    }
}
