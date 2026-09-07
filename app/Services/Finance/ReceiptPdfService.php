<?php

namespace App\Services\Finance;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\Pdf\PdfRenderService;
use App\Services\Storage\ObjectStorageService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

class ReceiptPdfService
{
    public function __construct(
        private readonly ObjectStorageService $storage,
        private readonly PdfRenderService $pdf,
    ) {}

    /**
     * Render a language-aware receipt into object storage. Tries a real PDF via
     * DomPDF first (path keyed by payment id: receipts/{paymentId}.pdf) and
     * falls back to the same content as HTML (receipts/{paymentId}.html) when
     * the renderer is unavailable — the same optional-degradation treatment S4
     * gives CredentialService, applied here per the plan doc's note that this
     * service has the identical shortcut.
     */
    public function generate(Payment $payment): string
    {
        $payment->loadMissing(['student', 'invoice']);

        $locale = $payment->student?->preferred_locale
            ?: app()->getLocale();

        $previous = app()->getLocale();
        App::setLocale($locale);

        try {
            $html = view('finance.receipt-document', [
                'payment' => $payment,
                'locale' => $locale,
                'isRtl' => $locale === 'ar',
            ])->render();
        } finally {
            App::setLocale($previous);
        }

        $pdfBytes = $this->pdf->renderPdf($html);

        if ($pdfBytes !== null) {
            $path = 'receipts/'.$payment->id.'.pdf';
            $this->storage->store($path, $pdfBytes);
        } else {
            $path = 'receipts/'.$payment->id.'.html';
            $this->storage->store($path, $html);
        }

        if ($payment->receipt_url !== $path) {
            $payment->update(['receipt_url' => $path]);
        }

        return $path;
    }

    /**
     * Ensure a stored receipt exists; generate on demand when missing.
     * Completed payments without a serial get one here so the HTML receipt link can render.
     */
    public function ensure(Payment $payment): string
    {
        if ($payment->receipt_serial === null && $payment->status === PaymentStatus::Completed) {
            $payment->update([
                'receipt_serial' => $this->allocateSerial(),
                'receipt_url' => $payment->receipt_url ?: 'receipts/'.$payment->id.'.html',
            ]);
            $payment->refresh();
        }

        $path = $payment->receipt_url ?: ('receipts/'.$payment->id.'.html');

        if (! $this->storage->exists($path)) {
            return $this->generate($payment);
        }

        return $path;
    }

    public function allocateSerial(): string
    {
        return DB::transaction(function () {
            $year = now()->format('Y');
            $setting = Setting::query()->lockForUpdate()->find('finance.receipt_counter');
            if ($setting === null) {
                $setting = new Setting(['key' => 'finance.receipt_counter']);
            }

            $value = $setting->value ?? [];
            $counters = $value['years'] ?? [];
            $next = ((int) ($counters[$year] ?? 0)) + 1;
            $counters[$year] = $next;
            $setting->value = ['years' => $counters];
            $setting->save();

            return sprintf('SPIMS-%s-%05d', $year, $next);
        });
    }
}
