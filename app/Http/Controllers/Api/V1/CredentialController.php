<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Credential;
use App\Services\Credentials\CredentialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CredentialController extends Controller
{
    public function index(Request $request, CredentialService $credentials): JsonResponse
    {
        $items = $credentials->forStudent($request->user());

        return response()->json([
            'data' => $items->map(fn (Credential $credential) => $this->payload($credential))->values(),
        ]);
    }

    public function download(Request $request, Credential $credential, CredentialService $credentials): Response
    {
        // Existence is private on reads: a non-owner must not learn that this
        // serial exists. The owner needs no extra permission key — download()
        // already allows the student who holds the credential.
        if ($credential->student_id !== $request->user()->id) {
            abort(404);
        }

        $file = $credentials->download($request->user(), $credential);

        return response($file['contents'], 200, [
            'Content-Type' => $file['mime'],
            'Content-Disposition' => 'attachment; filename="'.$file['filename'].'"',
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(Credential $credential): array
    {
        return [
            'id' => $credential->id,
            'type' => $credential->type->value,
            'serial' => $credential->serial,
            'language' => $credential->language,
            'offering_id' => $credential->offering_id,
            'program_id' => $credential->program_id,
            'issued_at' => $credential->issued_at?->toIso8601String(),
            'verify_url' => $credential->verifyUrl(),
        ];
    }
}
