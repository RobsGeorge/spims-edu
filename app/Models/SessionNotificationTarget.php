<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionNotificationTarget extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'class_session_id',
        'user_id',
        'notified_at',
    ];

    protected $casts = [
        'notified_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class, 'class_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
