<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use App\Services\Storage\ObjectStorageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Theme extends Model
{
    use HasUlids;

    protected $fillable = [
        'name',
        'is_active',
        'site_name',
        'logo_light_url',
        'logo_dark_url',
        'favicon_url',
        'tokens',
        'updated_by_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'tokens' => 'array',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    public function resolvedLogoLightUrl(): ?string
    {
        return $this->resolvedAsset($this->logo_light_url);
    }

    public function resolvedLogoDarkUrl(): ?string
    {
        return $this->resolvedAsset($this->logo_dark_url);
    }

    public function resolvedFaviconUrl(): ?string
    {
        return $this->resolvedAsset($this->favicon_url);
    }

    public function resolvedAsset(?string $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://') || str_starts_with($value, '/')) {
            return $value;
        }

        return app(ObjectStorageService::class)->temporaryUrl($value);
    }
}
