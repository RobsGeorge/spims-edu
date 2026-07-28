<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradingScheme extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'name',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function bands(): HasMany
    {
        return $this->hasMany(GradeBand::class, 'scheme_id');
    }
}
