<?php

namespace App\Http\Controllers;

use App\Models\Credential;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public API endpoint — resolve a credential by its printed serial number.
 * Used by the homepage verifier widget.
 */
class CredentialVerifySerialController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $serial = trim($request->query('serial', ''));

        if ($serial === '') {
            return response()->json(['found' => false, 'valid' => false, 'message' => 'serial_required'], 422);
        }

        $credential = Credential::query()
            ->with(['student:id,first_name,last_name', 'program:id,name'])
            ->where('serial', $serial)
            ->first();

        if (! $credential) {
            return response()->json(['found' => false, 'valid' => false]);
        }

        return response()->json([
            'found'     => true,
            'valid'     => $credential->isValid(),
            'serial'    => $credential->serial,
            'type'      => $credential->type->value,
            'issued_at' => $credential->issued_at?->toDateString(),
        ]);
    }
}
