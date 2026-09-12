<?php

namespace App\Http\Controllers;

use App\Models\Credential;
use App\Services\Credentials\CredentialService;
use Illuminate\View\View;

class CredentialVerifyController extends Controller
{
    public function __invoke(string $token, CredentialService $credentials): View
    {
        $credential = $credentials->findByQrToken($token);

        return view('credentials.verify', [
            'credential' => $credential,
            'valid' => $credential?->isValid() ?? false,
            // L8 — a legacy-imported credential is historical: distinct /verify copy,
            // never eligible for reissue. See docs/legacy-data-import-plan.md §22.1.
            'isLegacy' => $credential?->isLegacy() ?? false,
        ]);
    }
}
