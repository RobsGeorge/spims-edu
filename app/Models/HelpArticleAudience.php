<?php

namespace App\Models;

use App\Enums\RoleType;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpArticleAudience extends Model
{
    /** @use HasFactory<\Database\Factories\HelpArticleAudienceFactory> */
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'article_id',
        'role',
    ];

    protected $casts = [
        'role' => RoleType::class,
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(HelpArticle::class, 'article_id');
    }
}
