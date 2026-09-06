<?php

namespace App\Support\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Weak ETag for read-only teach collections. Confirmation-issuing GETs must not
 * use this — their body changes when a token is minted.
 */
class ConditionalGet
{
    /**
     * @param  array<string, mixed>  $body
     */
    public function json(Request $request, array $body, int $status = 200): JsonResponse
    {
        $etag = hash('sha256', json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $incoming = $this->incomingMatch($request);
        if ($incoming !== '' && hash_equals($etag, $incoming)) {
            return (new JsonResponse(null, 304))->setEtag($etag);
        }

        return response()->json($body, $status)->setEtag($etag);
    }

    private function incomingMatch(Request $request): string
    {
        $raw = (string) $request->header('If-None-Match');
        if ($raw === '') {
            return '';
        }
        if (str_starts_with($raw, 'W/')) {
            $raw = substr($raw, 2);
        }

        return trim($raw, " \t\"'");
    }
}
