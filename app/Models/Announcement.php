<?php

namespace App\Models;

use App\Enums\AnnouncementStatus;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Announcement extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'offering_id',
        'author_id',
        'title',
        'body',
        'body_ar',
        'body_fr',
        'status',
        'published_at',
        'published_by_id',
        'is_banner',
        'banner_expires_at',
    ];

    protected $casts = [
        'status' => AnnouncementStatus::class,
        'is_banner' => 'boolean',
        'published_at' => 'datetime',
        'banner_expires_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function offering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class, 'offering_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(AnnouncementRevision::class)->orderByDesc('created_at');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(AnnouncementTarget::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(AnnouncementDelivery::class);
    }

    public function isPublished(): bool
    {
        return $this->status === AnnouncementStatus::Published;
    }

    public function isBannerActive(): bool
    {
        if (! $this->is_banner || ! $this->isPublished()) {
            return false;
        }

        return $this->banner_expires_at === null || $this->banner_expires_at->isFuture();
    }

    public function localizedBody(?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();

        return match ($locale) {
            'ar' => $this->body_ar ?: $this->body,
            'fr' => $this->body_fr ?: $this->body,
            default => $this->body,
        };
    }
}
