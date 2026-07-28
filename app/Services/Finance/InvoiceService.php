<?php

namespace App\Services\Finance;

use App\Enums\Currency;
use App\Enums\InvoiceStatus;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\User;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
    ) {}

    /**
     * System side-effect of enrollment — no finance.invoices permission required.
     */
    public function createForEnrollment(User $actor, Enrollment $enrollment, ?string $countryCode = null): Invoice
    {
        $enrollment->loadMissing(['offering.course', 'student']);
        $offering = $enrollment->offering;
        $price = $offering->resolvedPriceForCountry($countryCode ?? $enrollment->student->country_code);
        /** @var Currency $currency */
        $currency = $price['currency'];
        $amount = (int) $price['amount_minor'];

        if ($amount === 0 || $offering->course->is_free) {
            return $this->audit->withAudit($actor, 'finance.invoice_free', function () use ($enrollment, $currency) {
                return Invoice::query()->updateOrCreate(
                    ['enrollment_id' => $enrollment->id],
                    [
                        'student_id' => $enrollment->student_id,
                        'currency' => $currency,
                        'total_minor' => 0,
                        'status' => InvoiceStatus::Paid,
                        'due_date' => null,
                    ]
                );
            }, 'Invoice');
        }

        return DB::transaction(function () use ($actor, $enrollment, $offering, $currency, $amount) {
            $invoice = Invoice::query()->updateOrCreate(
                ['enrollment_id' => $enrollment->id],
                [
                    'student_id' => $enrollment->student_id,
                    'currency' => $currency,
                    'total_minor' => $amount,
                    'status' => InvoiceStatus::Open,
                    'due_date' => now()->addDays(14),
                ]
            );

            InvoiceLine::query()->where('invoice_id', $invoice->id)->delete();
            InvoiceLine::query()->create([
                'invoice_id' => $invoice->id,
                'description' => $offering->course->code.' — '.$offering->course->title,
                'offering_id' => $offering->id,
                'amount_minor' => $amount,
            ]);

            $this->audit->write($actor, 'finance.invoice_create', 'Invoice', $invoice->id);

            return $invoice->fresh('lines');
        });
    }

    public function createManual(User $actor, User $student, Currency $currency, int $totalMinor, string $description): Invoice
    {
        $this->authorize->authorize($actor, 'finance.invoices');

        return $this->audit->withAudit($actor, 'finance.invoice_create', function () use ($student, $currency, $totalMinor, $description) {
            $invoice = Invoice::query()->create([
                'student_id' => $student->id,
                'currency' => $currency,
                'total_minor' => $totalMinor,
                'status' => InvoiceStatus::Open,
                'due_date' => now()->addDays(14),
            ]);

            InvoiceLine::query()->create([
                'invoice_id' => $invoice->id,
                'description' => $description,
                'amount_minor' => $totalMinor,
            ]);

            return $invoice->load('lines');
        }, 'Invoice');
    }

    public function refreshStatus(Invoice $invoice): Invoice
    {
        $paid = $invoice->amountPaid();

        $status = match (true) {
            $paid <= 0 => InvoiceStatus::Open,
            $paid < $invoice->total_minor => InvoiceStatus::Partial,
            default => InvoiceStatus::Paid,
        };

        if ($invoice->status === InvoiceStatus::Void || $invoice->status === InvoiceStatus::Refunded) {
            return $invoice;
        }

        $invoice->update(['status' => $status]);

        return $invoice->fresh();
    }

    public function markRefunded(Invoice $invoice): void
    {
        $invoice->update(['status' => InvoiceStatus::Refunded]);
    }
}
