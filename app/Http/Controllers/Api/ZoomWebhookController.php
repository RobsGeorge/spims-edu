<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LiveSession;
use App\Models\User;
use App\Services\Live\AttendanceService;
use App\Services\Live\ZoomClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZoomWebhookController extends Controller
{
    public function __invoke(Request $request, ZoomClient $zoom, AttendanceService $attendance): JsonResponse
    {
        $signature = (string) $request->header('x-zm-signature', '');
        $timestamp = (string) $request->header('x-zm-request-timestamp', '');
        $body = $request->getContent();

        if (! $zoom->verifyWebhookSecret($signature, $timestamp, $body)) {
            return response()->json(['ok' => false, 'error' => 'invalid signature'], 401);
        }

        $event = $request->input('event');
        $payload = $request->input('payload', []);

        if ($event === 'endpoint.url_validation') {
            $plain = $payload['plainToken'] ?? '';
            $secret = config('services.zoom.webhook_secret', 'zoom-test');

            return response()->json([
                'plainToken' => $plain,
                'encryptedToken' => hash_hmac('sha256', $plain, $secret),
            ]);
        }

        $meetingId = (string) ($payload['object']['id'] ?? $payload['meeting_id'] ?? '');
        $session = LiveSession::query()->where('zoom_meeting_id', $meetingId)->first();

        if ($session === null) {
            return response()->json(['ok' => true, 'ignored' => true]);
        }

        if ($event === 'recording.completed') {
            $url = $payload['object']['share_url']
                ?? $payload['object']['recording_files'][0]['play_url']
                ?? null;
            if ($url) {
                $attendance->attachRecording($session, $url);
            }
        }

        if ($event === 'meeting.participant_report' || $event === 'meeting.ended') {
            $participants = [];
            foreach ($payload['object']['participants'] ?? $payload['participants'] ?? [] as $p) {
                $participants[] = [
                    'email' => $p['user_email'] ?? $p['email'] ?? null,
                    'user_id' => $p['spims_user_id'] ?? null,
                    'minutes' => (int) ($p['duration'] ?? $p['minutes'] ?? 0),
                ];
            }

            if ($participants !== []) {
                $actor = User::query()->where('email', config('spims.superadmin_email', env('SUPERADMIN_EMAIL')))->first()
                    ?? User::query()->first();
                if ($actor) {
                    $attendance->importFromZoom($actor, $session, $participants);
                }
            }
        }

        return response()->json(['ok' => true]);
    }
}
