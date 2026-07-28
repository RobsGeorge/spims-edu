<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'body',
        'channel',
        'metadata',
        'read_at',
    ];

    protected $casts = [
        'channel' => NotificationChannel::class,
        'metadata' => 'array',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
