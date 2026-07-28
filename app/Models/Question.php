<?php

namespace App\Models;

use App\Enums\QuestionType;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'bank_id',
        'type',
        'prompt',
        'points',
        'config',
        'ai_key_points',
        'ai_guidance',
    ];

    protected $casts = [
        'type' => QuestionType::class,
        'points' => 'float',
        'config' => 'array',
    ];

    public function bank(): BelongsTo
    {
        return $this->belongsTo(QuestionBank::class, 'bank_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('order');
    }

    public function isObjective(): bool
    {
        return in_array($this->type, [
            QuestionType::McqSingle,
            QuestionType::McqMulti,
            QuestionType::TrueFalse,
            QuestionType::Numeric,
            QuestionType::FillBlank,
            QuestionType::Matching,
            QuestionType::Ordering,
            QuestionType::ShortAnswer,
        ], true);
    }
}
