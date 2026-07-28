<?php

namespace App\Services\Assessment;

use App\Enums\QuestionType;
use App\Models\Course;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\User;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuestionBankService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
    ) {}

    public function createBank(User $actor, Course $course, string $name): QuestionBank
    {
        $this->authorize->authorize($actor, 'questions.manage');

        return $this->audit->withAudit($actor, 'questions.bank_create', function () use ($course, $name) {
            return QuestionBank::query()->create([
                'course_id' => $course->id,
                'name' => $name,
            ]);
        }, 'QuestionBank');
    }

    /**
     * @param  array{type: string, prompt: string, points?: float, config?: array, ai_key_points?: string, ai_guidance?: string, options?: array<int, array{text: string, is_correct?: bool, match_key?: string, order?: int}>}  $data
     */
    public function addQuestion(User $actor, QuestionBank $bank, array $data): Question
    {
        $this->authorize->authorize($actor, 'questions.manage');

        return DB::transaction(function () use ($actor, $bank, $data) {
            $question = Question::query()->create([
                'bank_id' => $bank->id,
                'type' => QuestionType::from($data['type']),
                'prompt' => $data['prompt'],
                'points' => $data['points'] ?? 1,
                'config' => $data['config'] ?? null,
                'ai_key_points' => $data['ai_key_points'] ?? null,
                'ai_guidance' => $data['ai_guidance'] ?? null,
            ]);

            foreach ($data['options'] ?? [] as $i => $opt) {
                QuestionOption::query()->create([
                    'question_id' => $question->id,
                    'text' => $opt['text'],
                    'is_correct' => (bool) ($opt['is_correct'] ?? false),
                    'match_key' => $opt['match_key'] ?? null,
                    'order' => $opt['order'] ?? $i,
                ]);
            }

            $this->audit->write($actor, 'questions.create', 'Question', $question->id);

            return $question->load('options');
        });
    }
}
