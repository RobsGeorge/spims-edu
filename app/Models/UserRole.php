<?php

namespace App\Models;

use App\Enums\RoleType;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRole extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'role',
    ];

    protected $casts = [
        'role' => RoleType::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
