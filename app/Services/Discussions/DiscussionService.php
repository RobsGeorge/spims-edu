<?php

namespace App\Services\Discussions;

use App\Enums\OfferingMode;
use App\Enums\RoleType;
use App\Enums\ThreadVisibility;
use App\Models\CourseOffering;
use App\Models\DiscussionBoard;
use App\Models\DiscussionGrade;
use App\Models\DiscussionPost;
use App\Models\DiscussionThread;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DiscussionService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
        private readonly NotificationService $notifications,
    ) {}

    public function ensureBoard(CourseOffering $offering): DiscussionBoard
    {
        $allowStudents = $offering->mode !== OfferingMode::SelfPaced;

        return DiscussionBoard::query()->firstOrCreate(
            ['offering_id' => $offering->id],
            ['allow_student_threads' => $allowStudents]
        );
    }

    public function configureBoard(User $actor, CourseOffering $offering, bool $allowStudentThreads): DiscussionBoard
    {
        $this->authorize->authorize($actor, 'discussions.configure');

        $board = $this->ensureBoard($offering);
        $board->update(['allow_student_threads' => $allowStudentThreads]);
        $this->audit->write($actor, 'discussions.configure', 'DiscussionBoard', $board->id);

        return $board->fresh();
    }

    /**
     * @param  array{title: string, visibility?: string, is_graded?: bool, participation_min_words?: int, participation_min_posts?: int, participation_min_replies?: int, body?: string}  $data
     */
    public function createThread(User $actor, DiscussionBoard $board, array $data): DiscussionThread
    {
        $this->authorize->authorize($actor, 'discussions.thread');

        $isStaff = $actor->isSuperAdmin()
            || $actor->hasRole(RoleType::AcademicAdmin)
            || $actor->hasRole(RoleType::Instructor)
            || $actor->hasRole(RoleType::Ta);

        if (! $isStaff && ! $board->allow_student_threads) {
            throw ValidationException::withMessages(['thread' => [__('live.threads_disabled')]]);
        }

        return DB::transaction(function () use ($actor, $board, $data) {
            $thread = DiscussionThread::query()->create([
                'board_id' => $board->id,
                'author_id' => $actor->id,
                'title' => $data['title'],
                'visibility' => ThreadVisibility::from($data['visibility'] ?? ThreadVisibility::Open->value),
                'is_graded' => (bool) ($data['is_graded'] ?? false),
                'participation_min_words' => $data['participation_min_words'] ?? null,
                'participation_min_posts' => $data['participation_min_posts'] ?? null,
                'participation_min_replies' => $data['participation_min_replies'] ?? null,
                'locked' => false,
                'pinned' => false,
            ]);

            if (! empty($data['body'])) {
                DiscussionPost::query()->create([
                    'thread_id' => $thread->id,
                    'author_id' => $actor->id,
                    'body' => $data['body'],
                ]);
            }

            $this->audit->write($actor, 'discussions.thread_create', 'DiscussionThread', $thread->id);

            return $thread->fresh('posts');
        });
    }

    public function post(User $actor, DiscussionThread $thread, string $body, ?string $parentPostId = null, ?array $attachments = null): DiscussionPost
    {
        $this->authorize->authorize($actor, 'discussions.post');

        if ($thread->locked) {
            throw ValidationException::withMessages(['post' => [__('live.thread_locked')]]);
        }

        $post = DiscussionPost::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $actor->id,
            'parent_post_id' => $parentPostId,
            'body' => $body,
            'attachments' => $attachments,
        ]);

        if ($parentPostId) {
            $parent = DiscussionPost::query()->find($parentPostId);
            if ($parent && $parent->author_id !== $actor->id) {
                $this->notifications->notify(
                    User::query()->findOrFail($parent->author_id),
                    'discussions.reply',
                    __('live.reply_title'),
                    __('live.reply_body', ['thread' => $thread->title]),
                    ['thread_id' => $thread->id, 'post_id' => $post->id]
                );
            }
        }

        if (preg_match_all('/@([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/', $body, $matches)) {
            foreach (array_unique($matches[1]) as $email) {
                $mentioned = User::query()->where('email', $email)->first();
                if ($mentioned && $mentioned->id !== $actor->id) {
                    $this->notifications->notify(
                        $mentioned,
                        'discussions.mention',
                        __('live.mention_title'),
                        __('live.mention_body', ['thread' => $thread->title]),
                        ['thread_id' => $thread->id, 'post_id' => $post->id]
                    );
                }
            }
        }

        if ($thread->is_graded) {
            $this->autoScoreStudent($thread, $actor);
        }

        $this->audit->write($actor, 'discussions.post', 'DiscussionPost', $post->id);

        return $post;
    }

    public function moderate(User $actor, DiscussionThread $thread, array $flags): DiscussionThread
    {
        $this->authorize->authorize($actor, 'discussions.moderate');

        $thread->update(array_intersect_key($flags, array_flip(['locked', 'pinned'])));
        $this->audit->write($actor, 'discussions.moderate', 'DiscussionThread', $thread->id, null, $flags);

        return $thread->fresh();
    }

    public function autoScoreStudent(DiscussionThread $thread, User $student): DiscussionGrade
    {
        $posts = DiscussionPost::query()
            ->where('thread_id', $thread->id)
            ->where('author_id', $student->id)
            ->get();

        $words = $posts->sum(fn (DiscussionPost $p) => str_word_count(strip_tags($p->body)));
        $postCount = $posts->whereNull('parent_post_id')->count();
        $replyCount = $posts->whereNotNull('parent_post_id')->count();

        $checks = 0;
        $passed = 0;
        if ($thread->participation_min_words !== null) {
            $checks++;
            if ($words >= $thread->participation_min_words) {
                $passed++;
            }
        }
        if ($thread->participation_min_posts !== null) {
            $checks++;
            if ($postCount >= $thread->participation_min_posts) {
                $passed++;
            }
        }
        if ($thread->participation_min_replies !== null) {
            $checks++;
            if ($replyCount >= $thread->participation_min_replies) {
                $passed++;
            }
        }

        $auto = $checks === 0 ? 100.0 : round(($passed / $checks) * 100, 2);

        $grade = DiscussionGrade::query()->firstOrNew([
            'thread_id' => $thread->id,
            'student_id' => $student->id,
        ]);

        $grade->auto_score = $auto;
        if (! $grade->overridden) {
            $grade->final_score = $auto;
        }
        $grade->save();

        return $grade->fresh();
    }

    public function overrideGrade(User $actor, DiscussionThread $thread, User $student, float $score, ?string $feedback = null): DiscussionGrade
    {
        $this->authorize->authorize($actor, 'discussions.grade');

        $grade = DiscussionGrade::query()->updateOrCreate(
            ['thread_id' => $thread->id, 'student_id' => $student->id],
            [
                'final_score' => $score,
                'overridden' => true,
                'feedback' => $feedback,
                'graded_by_id' => $actor->id,
                'graded_at' => now(),
            ]
        );

        $this->audit->write($actor, 'discussions.grade_override', 'DiscussionGrade', $grade->id);

        return $grade;
    }

    public function studentThreadScore(DiscussionThread $thread, User $student): ?float
    {
        return DiscussionGrade::query()
            ->where('thread_id', $thread->id)
            ->where('student_id', $student->id)
            ->value('final_score');
    }
}
