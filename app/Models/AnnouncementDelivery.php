<?php

namespace App\Models;

use App\Enums\CommunicationChannel;
use App\Enums\DeliveryStatus;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementDelivery extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'announcement_id',
        'recipient_id',
        'channel',
        'status',
        'sent_at',
        'read_at',
        'opened_at',
        'error',
    ];

    protected $casts = [
        'channel' => CommunicationChannel::class,
        'status' => DeliveryStatus::class,
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
        'opened_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }
}
