<?php

namespace App\Models;

use App\Enums\AnnouncementTargetType;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementTarget extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'announcement_id',
        'target_type',
        'target_id',
    ];

    protected $casts = [
        'target_type' => AnnouncementTargetType::class,
        'created_at' => 'datetime',
    ];

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }
}
