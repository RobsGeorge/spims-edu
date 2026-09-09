<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpArticleLocale extends Model
{
    /** @use HasFactory<\Database\Factories\HelpArticleLocaleFactory> */
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'article_id',
        'locale',
        'title',
        'summary',
        'body_markdown',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(HelpArticle::class, 'article_id');
    }
}
