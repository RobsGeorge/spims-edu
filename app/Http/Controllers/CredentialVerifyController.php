<?php

namespace App\Http\Controllers;

use App\Models\Credential;
use App\Services\Credentials\CredentialService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CredentialVerifyController extends Controller
{
    public function __invoke(string $token, CredentialService $credentials): View
    {
        $credential = $credentials->findByQrToken($token);

        return view('credentials.verify', [
            'credential' => $credential,
            'valid' => $credential?->isValid() ?? false,
        ]);
    }
}
