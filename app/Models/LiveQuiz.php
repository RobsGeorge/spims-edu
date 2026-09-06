<?php

namespace App\Models;

use App\Enums\LiveQuizStatus;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveQuiz extends Model
{
    use HasUlids;

    protected $fillable = [
        'offering_id',
        'title',
        'status',
        'created_by',
    ];

    protected $casts = [
        'status' => LiveQuizStatus::class,
    ];

    public function offering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class, 'offering_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(LiveQuizQuestion::class, 'quiz_id')->orderBy('position');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(LiveQuizSession::class, 'quiz_id');
    }
}
