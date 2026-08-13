<?php

namespace App\Models;

use App\Enums\RoleType;
use App\Enums\ThemePreference;
use App\Enums\UserStatus;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens;
    use HasFactory;
    use HasUlids;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'email',
        'phone',
        'first_name',
        'last_name',
        'password_hash',
        'email_verified',
        'preferred_locale',
        'theme_preference',
        'notify_email',
        'country_code',
        'status',
        'is_reviewer',
        'last_reviewed_at',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected $casts = [
        'email_verified' => 'boolean',
        'theme_preference' => ThemePreference::class,
        'notify_email' => 'boolean',
        'status' => UserStatus::class,
        'is_reviewer' => 'boolean',
        'last_reviewed_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function roles(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    public function hasRole(RoleType $role): bool
    {
        return $this->roles->contains(fn (UserRole $userRole): bool => $userRole->role === $role);
    }

    public function roleTypes(): Collection
    {
        return $this->roles->pluck('role');
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(RoleType::SuperAdmin);
    }

    public function getAuthPassword(): string
    {
        return (string) $this->password_hash;
    }
}
