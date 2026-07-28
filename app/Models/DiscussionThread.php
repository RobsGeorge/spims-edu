<?php

namespace App\Models;

use App\Enums\ThreadVisibility;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscussionThread extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'board_id',
        'author_id',
        'title',
        'visibility',
        'is_graded',
        'participation_min_words',
        'participation_min_posts',
        'participation_min_replies',
        'locked',
        'pinned',
    ];

    protected $casts = [
        'visibility' => ThreadVisibility::class,
        'is_graded' => 'boolean',
        'participation_min_words' => 'integer',
        'participation_min_posts' => 'integer',
        'participation_min_replies' => 'integer',
        'locked' => 'boolean',
        'pinned' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function board(): BelongsTo
    {
        return $this->belongsTo(DiscussionBoard::class, 'board_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(DiscussionPost::class, 'thread_id');
    }

    public function grades(): HasMany
    {
        return $this->hasMany(DiscussionGrade::class, 'thread_id');
    }
}
