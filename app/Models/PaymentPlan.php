<?php

namespace App\Models;

use App\Enums\PaymentPlanInterval;
use App\Enums\PaymentPlanStatus;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentPlan extends Model
{
    use HasUlids;

    protected $fillable = [
        'invoice_id',
        'installment_count',
        'interval',
        'start_on',
        'status',
    ];

    protected $casts = [
        'interval' => PaymentPlanInterval::class,
        'status' => PaymentPlanStatus::class,
        'installment_count' => 'integer',
        'start_on' => 'date',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(PaymentPlanInstallment::class)->orderBy('due_on');
    }

    public function isOpen(): bool
    {
        return $this->status === PaymentPlanStatus::Open;
    }
}
