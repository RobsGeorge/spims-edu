<?php

namespace App\Models;

use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApplicationForm extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'program_id',
        'name',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(ApplicationFormField::class, 'form_id')->orderBy('order');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'form_id');
    }
}
