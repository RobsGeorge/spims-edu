<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpMedia extends Model
{
    /** @use HasFactory<\Database\Factories\HelpMediaFactory> */
    use HasFactory;
    use HasUlids;

    protected $table = 'help_media';

    protected $fillable = [
        'article_id',
        'path',
        'alt',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(HelpArticle::class, 'article_id');
    }
}
