<?php

namespace App\Http\Controllers;

use App\Models\Credential;
use App\Services\Credentials\CredentialService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CredentialDownloadController extends Controller
{
    public function __invoke(Request $request, Credential $credential, CredentialService $credentials): Response
    {
        // Owner downloads their own file; anyone else needs credentials.issue.
        $file = $credentials->download($request->user(), $credential);

        return response($file['contents'], 200, [
            'Content-Type' => $file['mime'],
            'Content-Disposition' => 'attachment; filename="'.$file['filename'].'"',
        ]);
    }
}
