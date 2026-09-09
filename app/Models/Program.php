<?php

namespace App\Models;

use App\Enums\ProgramType;
use App\Models\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Program extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'type',
        'level',
        'passing_threshold',
        'require_all_courses_to_graduate',
        'max_credits_per_semester',
        'max_courses_per_semester',
        'max_semesters_to_graduate',
        'elective_credits_required',
        'signatory_name',
        'signatory_title',
        'certificate_template',
        'issue_credential_on_completion',
        'grading_scheme_id',
        'active',
        'description',
        'marketing_summary',
        'standing_good_min',
        'standing_suspension_below',
        'enforce_year_sequence',
    ];

    protected $casts = [
        'type' => ProgramType::class,
        'passing_threshold' => 'float',
        'max_credits_per_semester' => 'integer',
        'max_courses_per_semester' => 'integer',
        'max_semesters_to_graduate' => 'integer',
        'elective_credits_required' => 'integer',
        'active' => 'boolean',
        'require_all_courses_to_graduate' => 'boolean',
        'issue_credential_on_completion' => 'boolean',
        // Nullable override columns stay uncast — Laravel's integer cast turns null into 0.
        'enforce_year_sequence' => 'boolean',
    ];

    /**
     * Build the human-readable rule-preview sentence for this program.
     * Must produce the same output as the Alpine client-side preview in the edit form.
     */
    public function rulePreviewSentence(): string
    {
        $credits = $this->max_credits_per_semester ?? '—';
        $semesters = $this->max_semesters_to_graduate ?? '—';
        $threshold = $this->passing_threshold ?? 60;
        $yearOrder = $this->enforce_year_sequence
            ? __('academics.rule_preview_year_blocked')
            : __('academics.rule_preview_year_warned');

        return __('academics.rule_preview_sentence', [
            'credits'   => $credits,
            'semesters' => $semesters,
            'threshold' => $threshold,
            'year_order' => $yearOrder,
        ]);
    }

    public function gradingScheme(): BelongsTo
    {
        return $this->belongsTo(GradingScheme::class);
    }

    public function programCourses(): HasMany
    {
        return $this->hasMany(ProgramCourse::class);
    }

    public function applicationForms(): HasMany
    {
        return $this->hasMany(ApplicationForm::class);
    }
}
