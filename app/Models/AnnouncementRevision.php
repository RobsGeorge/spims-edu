<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementRevision extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'announcement_id',
        'editor_id',
        'title',
        'body',
        'body_ar',
        'body_fr',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'editor_id');
    }
}
