<?php

namespace App\Models;

use App\Enums\ContentItemType;
use App\Enums\VideoProvider;
use App\Models\Concerns\HasUlids;
use App\Support\Content\ExternalReadingRef;
use App\Support\Content\ExternalReadingUrl;
use App\Support\Content\VideoUrlParser;
use Illuminate\Validation\ValidationException;
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

    public function isRemoteFile(): bool
    {
        $url = (string) $this->file_url;

        return $url !== '' && (str_starts_with($url, 'https://') || str_starts_with($url, 'http://'));
    }

    public function isStoredFile(): bool
    {
        return is_string($this->file_url) && $this->file_url !== '' && ! $this->isRemoteFile();
    }

    public function storedFileExtension(): ?string
    {
        if (! $this->isStoredFile()) {
            return null;
        }

        $ext = strtolower(pathinfo($this->file_url, PATHINFO_EXTENSION));

        return $ext !== '' ? $ext : null;
    }

    public function isStoredImage(): bool
    {
        return in_array($this->storedFileExtension(), ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
    }

    public function isStoredPdf(): bool
    {
        return $this->storedFileExtension() === 'pdf';
    }

    public function remoteReading(): ?ExternalReadingRef
    {
        if (! $this->isRemoteFile()) {
            return null;
        }

        try {
            return ExternalReadingUrl::parse($this->file_url);
        } catch (ValidationException) {
            return new ExternalReadingRef((string) $this->file_url, false, ExternalReadingUrl::KIND_OTHER);
        }
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
