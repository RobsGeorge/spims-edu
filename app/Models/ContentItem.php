<?php

namespace App\Models;

use App\Enums\ContentItemType;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentItem extends Model
{
    use HasUlids;

    protected $fillable = [
        'week_id',
        'type',
        'title',
        'order',
        'vimeo_id',
        'file_url',
        'body',
    ];

    protected $casts = [
        'type' => ContentItemType::class,
        'order' => 'integer',
    ];

    public function week(): BelongsTo
    {
        return $this->belongsTo(Week::class);
    }
}
