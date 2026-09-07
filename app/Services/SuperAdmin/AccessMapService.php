<?php

namespace App\Services\SuperAdmin;

use App\Enums\RoleType;
use App\Models\User;
use App\Models\UserRole;
use App\Services\Rbac\RolePermissionService;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccessMapService
{
    /** @var list<RoleType> */
    private const LEARNER_ROLES = [RoleType::Student, RoleType::Ta];

    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly RolePermissionService $rbac,
        private readonly AuditLogWriter $audit,
    ) {}

    public function authorize(User $actor): void
    {
        $this->authorize->authorize($actor, 'access.map');
    }

    /**
     * @return array{
     *     query: string,
     *     roles: list<array<string, mixed>>,
     *     superadmin: array{count: int, emails: list<string>},
     *     exclusive: list<array{key: string, group: string, granted_to: list<string>}>,
     *     leaks: list<array{key: string, role: string}>,
     *     elevated: list<array{key: string, role: string}>,
     *     lookup: list<array{key: string, group: string, roles: list<string>, defaults: list<string>}>,
     *     totals: array{keys: int, exclusive: int, leaks: int, elevated: int, drifted: int}
     * }
     */
    public function snapshot(?string $query = null): array
    {
        $query = trim((string) $query);
        $defaults = config('permissions', []);
        $matrix = $this->rbac->matrix();
        $keys = $this->rbac->permissionKeys();
        $counts = $this->roleUserCounts();

        $roles = [];
        $elevated = [];
        $drifted = 0;

        foreach ($this->rbac->editableRoles() as $role) {
            $row = $this->roleRow($role, $defaults, $matrix, $counts);
            if (! $row['matches_defaults']) {
                $drifted++;
            }
            foreach ($row['added'] as $key) {
                if (in_array($role, self::LEARNER_ROLES, true)) {
                    $elevated[] = ['key' => $key, 'role' => $role->value];
                }
            }
            $roles[] = $row;
        }

        $exclusive = [];
        $leaks = [];
        foreach ($defaults as $key => $map) {
            if (! is_array($map) || $map !== []) {
                continue;
            }
            $grantedTo = [];
            foreach ($this->rbac->editableRoles() as $role) {
                if ($this->roleHasKey($matrix, $role, $key)) {
                    $grantedTo[] = $role->value;
                    $leaks[] = ['key' => $key, 'role' => $role->value];
                }
            }
            $exclusive[] = [
                'key' => $key,
                'group' => $this->groupOf($key),
                'granted_to' => $grantedTo,
            ];
        }

        $lookup = [];
        if ($query !== '') {
            $needle = mb_strtolower($query);
            foreach ($keys as $key) {
                if (! str_contains(mb_strtolower($key), $needle)) {
                    continue;
                }
                $lookup[] = [
                    'key' => $key,
                    'group' => $this->groupOf($key),
                    'roles' => $this->rolesHolding($matrix, $key),
                    'defaults' => $this->defaultRoles($defaults, $key),
                ];
            }
        }

        return [
            'query' => $query,
            'roles' => $roles,
            'superadmin' => $this->superAdminSnapshot(),
            'exclusive' => $exclusive,
            'leaks' => $leaks,
            'elevated' => $elevated,
            'lookup' => $lookup,
            'totals' => [
                'keys' => count($keys),
                'exclusive' => count($exclusive),
                'leaks' => count($leaks),
                'elevated' => count($elevated),
                'drifted' => $drifted,
            ],
        ];
    }

    public function exportCsv(User $actor, ?string $query = null): StreamedResponse
    {
        $this->authorize($actor);
        $snapshot = $this->snapshot($query);

        $this->audit->write($actor, 'access.map.export', 'AccessMap', null, null, [
            'keys' => $snapshot['totals']['keys'],
            'query' => $snapshot['query'] !== '' ? $snapshot['query'] : null,
        ]);

        $headers = [
            __('access.col_role'),
            __('access.col_key'),
            __('access.col_in_defaults'),
            __('access.col_exclusive'),
        ];

        $rows = [];
        $defaults = config('permissions', []);
        $matrix = $this->rbac->matrix();
        foreach ($this->rbac->editableRoles() as $role) {
            foreach ($this->rbac->permissionKeys() as $key) {
                if (! $this->roleHasKey($matrix, $role, $key)) {
                    continue;
                }
                $map = is_array($defaults[$key] ?? null) ? $defaults[$key] : [];
                $rows[] = [
                    $role->value,
                    $key,
                    array_key_exists($role->value, $map) ? '1' : '0',
                    $map === [] ? '1' : '0',
                ];
            }
        }

        $filename = 'spims-access-map-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($headers, $rows): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array<string, array<string, string>>  $defaults
     * @param  array<string, array<string, string>>  $matrix
     * @param  array<string, int>  $counts
     * @return array<string, mixed>
     */
    private function roleRow(RoleType $role, array $defaults, array $matrix, array $counts): array
    {
        $current = [];
        foreach ($this->rbac->permissionKeys() as $key) {
            if ($this->roleHasKey($matrix, $role, $key)) {
                $current[] = $key;
            }
        }

        $shipped = [];
        foreach ($defaults as $key => $map) {
            if (is_array($map) && array_key_exists($role->value, $map)) {
                $shipped[] = $key;
            }
        }

        $added = array_values(array_diff($current, $shipped));
        $removed = array_values(array_diff($shipped, $current));

        return [
            'role' => $role->value,
            'users' => (int) ($counts[$role->value] ?? 0),
            'granted' => count($current),
            'default' => count($shipped),
            'matches_defaults' => $added === [] && $removed === [],
            'added' => $added,
            'removed' => $removed,
            'directory_url' => Route::has('admin.users.index')
                ? route('admin.users.index', ['role' => $role->value])
                : null,
            'hub_url' => Route::has('roles.hub')
                ? route('roles.hub').'#role-'.$role->value
                : null,
        ];
    }

    /**
     * @param  array<string, array<string, string>>  $matrix
     */
    private function roleHasKey(array $matrix, RoleType $role, string $key): bool
    {
        $level = $matrix[$key][$role->value] ?? null;

        return is_string($level) && $level !== '';
    }

    /**
     * @param  array<string, array<string, string>>  $matrix
     * @return list<string>
     */
    private function rolesHolding(array $matrix, string $key): array
    {
        $out = [];
        foreach ($this->rbac->editableRoles() as $role) {
            if ($this->roleHasKey($matrix, $role, $key)) {
                $out[] = $role->value;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @return list<string>
     */
    private function defaultRoles(array $defaults, string $key): array
    {
        $map = $defaults[$key] ?? [];
        if (! is_array($map)) {
            return [];
        }

        return array_values(array_map('strval', array_keys($map)));
    }

    private function groupOf(string $key): string
    {
        return str_contains($key, '.') ? explode('.', $key, 2)[0] : 'general';
    }

    /**
     * @return array<string, int>
     */
    private function roleUserCounts(): array
    {
        $counts = [];
        foreach (UserRole::query()->selectRaw('role, count(*) as aggregate')->groupBy('role')->get() as $row) {
            $role = $row->role instanceof RoleType ? $row->role->value : (string) $row->role;
            $counts[$role] = (int) $row->aggregate;
        }

        return $counts;
    }

    /**
     * @return array{count: int, emails: list<string>}
     */
    private function superAdminSnapshot(): array
    {
        $emails = User::query()
            ->whereHas('roles', fn ($query) => $query->where('role', RoleType::SuperAdmin))
            ->orderBy('email')
            ->pluck('email')
            ->map(fn ($email): string => (string) $email)
            ->values()
            ->all();

        return [
            'count' => count($emails),
            'emails' => $emails,
        ];
    }
}
