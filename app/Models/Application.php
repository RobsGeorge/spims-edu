<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Application extends Model
{
    use HasUlids;

    protected $fillable = [
        'applicant_id',
        'program_id',
        'form_id',
        'status',
        'reviewer_id',
        'decision_note',
        'submitted_at',
        'decided_at',
    ];

    protected $casts = [
        'status' => ApplicationStatus::class,
        'submitted_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_id');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(ApplicationForm::class, 'form_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(ApplicationFieldValue::class);
    }
}
