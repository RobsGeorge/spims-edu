<?php

namespace App\Models;

use App\Enums\CommunicationChannel;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'event_key',
        'channel',
        'enabled',
    ];

    protected $casts = [
        'channel' => CommunicationChannel::class,
        'enabled' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
