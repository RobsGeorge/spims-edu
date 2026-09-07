<?php

namespace App\Models;

use App\Enums\ContentItemType;
use App\Enums\VideoProvider;
use App\Models\Concerns\HasUlids;
use App\Support\Content\VideoUrlParser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ContentItem extends Model
{
    use HasUlids;

    protected $fillable = [
        'week_id',
        'type',
        'title',
        'order',
        'vimeo_id',
        'video_provider',
        'file_url',
        'body',
        'published',
        'published_at',
    ];

    protected $attributes = [
        'published' => true,
    ];

    protected $casts = [
        'type' => ContentItemType::class,
        'video_provider' => VideoProvider::class,
        'order' => 'integer',
        'published' => 'boolean',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (ContentItem $item): void {
            if ($item->vimeo_id && $item->video_provider === null) {
                $item->video_provider = VideoProvider::Vimeo;
            }

            if ($item->published && $item->published_at === null) {
                $item->published_at = now();
            }
        });
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('published', true);
    }

    public function isPublished(): bool
    {
        return (bool) $this->published;
    }

    public function videoIframeUrl(): ?string
    {
        if (! $this->vimeo_id) {
            return null;
        }

        $provider = $this->video_provider ?? VideoProvider::Vimeo;

        return VideoUrlParser::iframeUrl($provider, $this->vimeo_id);
    }

    public function week(): BelongsTo
    {
        return $this->belongsTo(Week::class);
    }

    public function assignment(): HasOne
    {
        return $this->hasOne(Assignment::class);
    }

    public function assessment(): HasOne
    {
        return $this->hasOne(Assessment::class);
    }
}
