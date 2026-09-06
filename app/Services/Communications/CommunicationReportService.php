<?php

namespace App\Services\Communications;

use App\Models\CommunicationLog;
use App\Models\User;
use App\Support\AuthorizeService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommunicationReportService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
    ) {}

    /**
     * @param  array{type?: string, channel?: string, status?: string, recipient_id?: string, locale?: string, from?: string, to?: string}  $filters
     */
    public function query(User $actor, array $filters = []): Builder
    {
        $this->authorize->authorize($actor, 'communications.report');

        $query = CommunicationLog::query()->with('recipient')->orderByDesc('created_at');

        foreach (['type', 'channel', 'status', 'recipient_id', 'locale'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        return $query;
    }

    /**
     * @param  array<string, string>  $filters
     */
    public function paginate(User $actor, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->query($actor, $filters)->paginate($perPage);
    }

    /**
     * @param  array<string, string>  $filters
     */
    public function exportCsv(User $actor, array $filters = []): StreamedResponse
    {
        $rows = $this->query($actor, $filters)->get();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'type', 'channel', 'recipient_id', 'subject', 'locale', 'status', 'opened_at', 'created_at', 'error']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->id,
                    $row->type,
                    $row->channel->value,
                    $row->recipient_id,
                    $row->subject,
                    $row->locale,
                    $row->status->value,
                    $row->opened_at?->toIso8601String(),
                    $row->created_at?->toIso8601String(),
                    $row->error,
                ]);
            }
            fclose($out);
        }, 'communications.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
