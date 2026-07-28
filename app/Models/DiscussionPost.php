<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DiscussionPost extends Model
{
    use HasUlids;
    use SoftDeletes;

    public const UPDATED_AT = null;

    protected $fillable = [
        'thread_id',
        'author_id',
        'parent_post_id',
        'body',
        'attachments',
        'edited_at',
    ];

    protected $casts = [
        'attachments' => 'array',
        'created_at' => 'datetime',
        'edited_at' => 'datetime',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(DiscussionThread::class, 'thread_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_post_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_post_id');
    }
}
