<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Finance\ReceiptPdfService;
use App\Services\Storage\ObjectStorageService;
use App\Support\Api\StudentRecordGuard;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PaymentController extends Controller
{
    public function receipt(
        Request $request,
        Payment $payment,
        ReceiptPdfService $receipts,
        ObjectStorageService $storage,
        StudentRecordGuard $guard,
    ): Response {
        $guard->ownRead($request->user(), $payment->student_id);
        abort_unless(filled($payment->receipt_serial), 404);

        $path = $receipts->ensure($payment);
        $contents = $storage->disk()->get($path);
        $isPdf = str_ends_with($path, '.pdf');

        return response($contents, 200, [
            'Content-Type' => $isPdf ? 'application/pdf' : 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.($payment->receipt_serial).($isPdf ? '.pdf' : '.html').'"',
        ]);
    }
}
