<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $table = 'audit_logs';

    protected $fillable = [
        'actor_id',
        'actor_role',
        'action',
        'entity_type',
        'entity_id',
        'before',
        'after',
        'ip',
        'user_agent',
        'request_id',
    ];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
        'created_at' => 'datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
