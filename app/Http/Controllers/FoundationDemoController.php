<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\AuditLogWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FoundationDemoController extends Controller
{
    public function mutate(Request $request, AuditLogWriter $audit): JsonResponse
    {
        $note = $request->validate(['note' => 'required|string|max:255'])['note'];

        $setting = $audit->withAudit(
            $request->user(),
            'foundation.demo.mutate',
            fn () => Setting::query()->updateOrCreate(
                ['key' => 'foundation.demo'],
                ['value' => ['note' => $note], 'updated_by_id' => $request->user()?->id]
            ),
            'Setting'
        );

        return response()->json(['ok' => true, 'key' => $setting->key]);
    }
}
