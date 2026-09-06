<?php

namespace App\Models;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationLogStatus;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationLog extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'type',
        'channel',
        'recipient_id',
        'subject',
        'locale',
        'status',
        'provider_message_id',
        'opened_at',
        'error',
        'metadata',
    ];

    protected $casts = [
        'channel' => CommunicationChannel::class,
        'status' => CommunicationLogStatus::class,
        'opened_at' => 'datetime',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }
}
