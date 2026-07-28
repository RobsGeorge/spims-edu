<?php

namespace App\Models;

use App\Enums\RoleType;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class RolePermission extends Model
{
    use HasUlids;

    protected $fillable = [
        'role',
        'permission_key',
        'level',
    ];

    protected $casts = [
        'role' => RoleType::class,
    ];
}
