<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\PaymentPlanInstallmentStatus;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentPlanInstallment extends Model
{
    use HasUlids;

    protected $fillable = [
        'payment_plan_id',
        'due_on',
        'amount_minor',
        'currency',
        'status',
        'payment_id',
        'dunning_sent_at',
    ];

    protected $casts = [
        'currency' => Currency::class,
        'status' => PaymentPlanInstallmentStatus::class,
        'amount_minor' => 'integer',
        'due_on' => 'date',
        'dunning_sent_at' => 'datetime',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PaymentPlan::class, 'payment_plan_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [
            PaymentPlanInstallmentStatus::Pending,
            PaymentPlanInstallmentStatus::Due,
            PaymentPlanInstallmentStatus::Overdue,
        ], true);
    }
}
