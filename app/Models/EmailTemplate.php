<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailTemplate extends Model
{
    use HasUlids;

    protected $fillable = [
        'scope_type',
        'scope_id',
        'key',
        'locale',
        'subject',
        'body',
        'updated_by_id',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    public function isGlobal(): bool
    {
        return $this->scope_type === null && $this->scope_id === null;
    }
}
