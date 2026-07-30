<?php

namespace App\Services\Finance;

use App\Models\Payment;
use App\Services\Storage\ObjectStorageService;
use Illuminate\Support\Facades\App;

class ReceiptPdfService
{
    public function __construct(
        private readonly ObjectStorageService $storage,
    ) {}

    /**
     * Render a language-aware HTML receipt into object storage.
     * Path is keyed by payment id (no schema change): receipts/{paymentId}.html
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

        $path = 'receipts/'.$payment->id.'.html';
        $this->storage->store($path, $html);

        if ($payment->receipt_url !== $path) {
            $payment->update(['receipt_url' => $path]);
        }

        return $path;
    }

    /**
     * Ensure a stored receipt exists; generate on demand when missing.
     */
    public function ensure(Payment $payment): string
    {
        $path = $payment->receipt_url ?: ('receipts/'.$payment->id.'.html');

        if (! $this->storage->exists($path)) {
            return $this->generate($payment);
        }

        return $path;
    }
}
