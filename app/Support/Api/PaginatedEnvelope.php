<?php

namespace App\Support\Api;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaginatedEnvelope
{
    /**
     * @return array{data: mixed, meta: array{page: int, per_page: int, total: int, last_page: int}}
     */
    public static function from(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => $paginator->items(),
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    public static function perPage(?int $requested, int $default = 20, int $max = 100): int
    {
        $value = $requested ?? $default;

        return max(1, min($max, $value));
    }
}
