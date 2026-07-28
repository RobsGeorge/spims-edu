<?php

namespace App\Services\Live;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Zoom Server-to-Server OAuth client — degrades to mock meetings when credentials are absent.
 */
class ZoomClient
{
    public function isConfigured(): bool
    {
        return filled(config('services.zoom.account_id'))
            && filled(config('services.zoom.client_id'))
            && filled(config('services.zoom.client_secret'));
    }

    /**
     * @return array{id: string, join_url: string, start_url: string}
     */
    public function createMeeting(string $topic, \DateTimeInterface $start, int $durationMinutes): array
    {
        if (! $this->isConfigured()) {
            $id = 'mock-'.Str::ulid();

            return [
                'id' => $id,
                'join_url' => 'https://zoom.test/j/'.$id,
                'start_url' => 'https://zoom.test/s/'.$id,
            ];
        }

        $token = $this->accessToken();
        $response = Http::withToken($token)->post('https://api.zoom.us/v2/users/me/meetings', [
            'topic' => $topic,
            'type' => 2,
            'start_time' => $start->format('Y-m-d\TH:i:s'),
            'duration' => $durationMinutes,
            'timezone' => config('app.timezone', 'UTC'),
        ]);

        if (! $response->successful()) {
            Log::warning('Zoom createMeeting failed', ['body' => $response->body()]);
            $id = 'fallback-'.Str::ulid();

            return [
                'id' => $id,
                'join_url' => 'https://zoom.test/j/'.$id,
                'start_url' => 'https://zoom.test/s/'.$id,
            ];
        }

        $data = $response->json();

        return [
            'id' => (string) $data['id'],
            'join_url' => $data['join_url'],
            'start_url' => $data['start_url'],
        ];
    }

    public function verifyWebhookSecret(string $signature, string $timestamp, string $body): bool
    {
        $secret = config('services.zoom.webhook_secret', 'zoom-test');
        $message = 'v0:'.$timestamp.':'.$body;
        $expected = 'v0='.hash_hmac('sha256', $message, $secret);

        return hash_equals($expected, $signature);
    }

    private function accessToken(): string
    {
        $response = Http::asForm()
            ->withBasicAuth(config('services.zoom.client_id'), config('services.zoom.client_secret'))
            ->post('https://zoom.us/oauth/token', [
                'grant_type' => 'account_credentials',
                'account_id' => config('services.zoom.account_id'),
            ]);

        return (string) $response->json('access_token');
    }
}
