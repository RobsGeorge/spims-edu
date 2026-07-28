<?php

namespace App\Http\Controllers;

use App\Models\Credential;
use App\Services\Credentials\CredentialService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TranscriptController extends Controller
{
    public function __invoke(Request $request, CredentialService $credentials): View
    {
        $user = $request->user();
        $data = $credentials->transcriptData($user);

        return view('credentials.transcript', [
            'student' => $user,
            'records' => $data['records'],
            'gpa' => $data['gpa'],
            'credentials' => Credential::query()
                ->where('student_id', $user->id)
                ->whereNull('revoked_at')
                ->latest('issued_at')
                ->get(),
        ]);
    }
}
