<?php

namespace App\Models;

use App\Enums\TranslationSource;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Translation extends Model
{
    use HasUlids;

    public const CREATED_AT = null;

    protected $fillable = [
        'entity_type',
        'entity_id',
        'field',
        'locale',
        'value',
        'source',
        'verified',
        'updated_by_id',
    ];

    protected $casts = [
        'source' => TranslationSource::class,
        'verified' => 'boolean',
        'updated_at' => 'datetime',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }
}
