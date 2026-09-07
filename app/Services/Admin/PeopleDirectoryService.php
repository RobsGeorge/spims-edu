<?php

namespace App\Services\Admin;

use App\Enums\InvoiceStatus;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\AuthorizeService;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PeopleDirectoryService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
    ) {}

    /**
     * @param  array{q?: string, role?: string, status?: string, locale?: string}  $filters
     */
    public function paginate(User $actor, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $this->authorize->authorize($actor, 'users.manage');

        $query = User::query()->with('roles')->orderByDesc('created_at');

        $term = trim((string) ($filters['q'] ?? ''));
        if ($term !== '') {
            $like = '%'.mb_strtolower($term).'%';
            $query->where(function ($inner) use ($like): void {
                $inner->whereRaw('LOWER(email) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(first_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                    ->orWhereRaw("LOWER(first_name || ' ' || last_name) LIKE ?", [$like]);
            });
        }

        $role = trim((string) ($filters['role'] ?? ''));
        if ($role !== '') {
            $query->whereHas('roles', fn ($roles) => $roles->where('role', $role));
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $locale = trim((string) ($filters['locale'] ?? ''));
        if ($locale !== '') {
            $query->where('preferred_locale', $locale);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @return array<string, mixed>
     */
    public function dossier(User $actor, User $target): array
    {
        $this->authorize->authorize($actor, 'users.manage');

        $target->load(['roles', 'studentPrograms.program']);

        $enrollments = $target->enrollments()
            ->with(['offering.course', 'offering.semester'])
            ->orderByDesc('enrolled_at')
            ->limit(20)
            ->get();

        $invoices = $target->invoices()
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $recentAudit = AuditLog::query()
            ->where('actor_id', $target->id)
            ->latest('created_at')
            ->limit(12)
            ->get();

        $lastLogin = AuditLog::query()
            ->where('actor_id', $target->id)
            ->whereIn('action', ['auth.login', 'auth.loginAs', 'auth.api_login'])
            ->latest('created_at')
            ->first();

        return [
            'enrollments' => $enrollments,
            'invoices' => $invoices,
            'invoiceSummary' => $this->invoiceSummary($invoices),
            'recentAudit' => $recentAudit,
            'lastLogin' => $lastLogin,
            'identitySessions' => $target->identitySessions()->orderByDesc('created_at')->get(),
            'studentPrograms' => $target->studentPrograms,
        ];
    }

    /**
     * @param  Collection<int, \App\Models\Invoice>  $invoices
     * @return list<array{currency: string, total: string, open: int, paid: int}>
     */
    private function invoiceSummary(Collection $invoices): array
    {
        $grouped = [];

        foreach ($invoices as $invoice) {
            $key = $invoice->currency->value;
            $grouped[$key] ??= [
                'currency' => $key,
                'total_minor' => 0,
                'open' => 0,
                'paid' => 0,
            ];
            $grouped[$key]['total_minor'] += (int) $invoice->total_minor;
            if (in_array($invoice->status, [InvoiceStatus::Open, InvoiceStatus::Partial], true)) {
                $grouped[$key]['open']++;
            }
            if ($invoice->status === InvoiceStatus::Paid) {
                $grouped[$key]['paid']++;
            }
        }

        return array_values(array_map(function (array $row): array {
            return [
                'currency' => $row['currency'],
                'total' => Money::fromMinor($row['total_minor'], \App\Enums\Currency::from($row['currency']))->format(),
                'open' => $row['open'],
                'paid' => $row['paid'],
            ];
        }, $grouped));
    }
}
