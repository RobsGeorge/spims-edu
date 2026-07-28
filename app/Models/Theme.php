<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Theme extends Model
{
    use HasUlids;

    protected $fillable = [
        'name',
        'is_active',
        'site_name',
        'logo_light_url',
        'logo_dark_url',
        'favicon_url',
        'tokens',
        'updated_by_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'tokens' => 'array',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }
}
