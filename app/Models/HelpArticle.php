<?php

namespace App\Models;

use App\Enums\HelpArticleStatus;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HelpArticle extends Model
{
    /** @use HasFactory<\Database\Factories\HelpArticleFactory> */
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'category_id',
        'slug',
        'status',
        'sort_order',
        'is_public',
        'published_at',
        'updated_by_id',
    ];

    protected $casts = [
        'status' => HelpArticleStatus::class,
        'sort_order' => 'integer',
        'is_public' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(HelpCategory::class, 'category_id');
    }

    public function locales(): HasMany
    {
        return $this->hasMany(HelpArticleLocale::class, 'article_id');
    }

    public function audiences(): HasMany
    {
        return $this->hasMany(HelpArticleAudience::class, 'article_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(HelpMedia::class, 'article_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    public function localeFor(string $locale, string $fallback = 'en'): ?HelpArticleLocale
    {
        $locales = $this->relationLoaded('locales')
            ? $this->locales
            : $this->locales()->get();

        return $locales->firstWhere('locale', $locale)
            ?? $locales->firstWhere('locale', $fallback)
            ?? $locales->first();
    }
}
