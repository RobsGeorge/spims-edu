<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentPlanStatus;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasUlids;

    protected $fillable = [
        'student_id',
        'enrollment_id',
        'currency',
        'total_minor',
        'status',
        'due_date',
        'source_system',
    ];

    protected $casts = [
        'currency' => Currency::class,
        'status' => InvoiceStatus::class,
        'total_minor' => 'integer',
        'due_date' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function paymentPlans(): HasMany
    {
        return $this->hasMany(PaymentPlan::class);
    }

    public function openPaymentPlan(): ?PaymentPlan
    {
        $this->loadMissing('paymentPlans.installments');

        return $this->paymentPlans->first(
            fn (PaymentPlan $plan) => $plan->status === PaymentPlanStatus::Open
        );
    }

    public function amountPaid(): int
    {
        return (int) $this->payments()
            ->where('status', \App\Enums\PaymentStatus::Completed)
            ->sum('amount_minor');
    }

    public function amountDue(): int
    {
        return max(0, $this->total_minor - $this->amountPaid());
    }
}
