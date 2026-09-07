<?php

namespace App\Services\Discussions;

use App\Enums\EnrollmentStatus;
use App\Enums\OfferingMode;
use App\Enums\RoleType;
use App\Enums\ThreadVisibility;
use App\Models\CourseOffering;
use App\Models\DiscussionBoard;
use App\Models\DiscussionGrade;
use App\Models\DiscussionPost;
use App\Models\DiscussionThread;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Learning\OfferingAccessService;
use App\Services\Notifications\NotificationService;
use App\Services\Storage\ObjectStorageService;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DiscussionService
{
    private const ATTACHMENT_PREFIX = 'discussion-attachments';

    /** @var list<string> */
    private const ALLOWED_EXTENSIONS = [
        'pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'txt', 'doc', 'docx',
    ];

    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
        private readonly NotificationService $notifications,
        private readonly OfferingAccessService $access,
        private readonly ObjectStorageService $storage,
    ) {}

    public function ensureBoard(CourseOffering $offering): ?DiscussionBoard
    {
        return DiscussionBoard::query()->where('offering_id', $offering->id)->first();
    }

    public function provisionBoard(User $actor, CourseOffering $offering): DiscussionBoard
    {
        $existing = $this->ensureBoard($offering);
        if ($existing) {
            return $existing;
        }

        $allowStudents = $offering->mode !== OfferingMode::SelfPaced;

        return $this->audit->withAudit(
            $actor,
            'discussions.board_provision',
            fn () => DiscussionBoard::query()->create([
                'offering_id' => $offering->id,
                'allow_student_threads' => $allowStudents,
            ]),
            'DiscussionBoard'
        );
    }

    public function configureBoard(User $actor, CourseOffering $offering, bool $allowStudentThreads): DiscussionBoard
    {
        $this->authorize->authorize($actor, 'discussions.configure', $offering);

        $board = $this->provisionBoard($actor, $offering);
        $board->update(['allow_student_threads' => $allowStudentThreads]);
        $this->audit->write($actor, 'discussions.configure', 'DiscussionBoard', $board->id);

        return $board->fresh();
    }

    /**
     * @param  array{title: string, visibility?: string, is_graded?: bool, participation_min_words?: int, participation_min_posts?: int, participation_min_replies?: int, body?: string, attachments?: list<array{path: string, name?: string, size?: int|null}>|null}  $data
     * @param  list<UploadedFile>  $files
     */
    public function createThread(User $actor, DiscussionBoard $board, array $data, array $files = []): DiscussionThread
    {
        $this->authorize->authorize($actor, 'discussions.thread');

        $isStaff = $actor->isSuperAdmin()
            || $actor->hasRole(RoleType::AcademicAdmin)
            || $actor->hasRole(RoleType::Instructor)
            || $actor->hasRole(RoleType::Ta);

        if (! $isStaff && ! $board->allow_student_threads) {
            throw ValidationException::withMessages(['thread' => [__('live.threads_disabled')]]);
        }

        return DB::transaction(function () use ($actor, $board, $data, $files) {
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
                $attachments = $data['attachments'] ?? null;
                if ($files !== []) {
                    $attachments = $this->storeUploadedAttachments($actor, $thread, $files);
                }

                DiscussionPost::query()->create([
                    'thread_id' => $thread->id,
                    'author_id' => $actor->id,
                    'body' => $data['body'],
                    'attachments' => $this->validatedStoredAttachments($actor, $attachments),
                ]);
            }

            $this->audit->write($actor, 'discussions.thread_create', 'DiscussionThread', $thread->id);

            return $thread->fresh('posts');
        });
    }

    /**
     * Threads a viewer may list on a board. Staff/admins see private-to-instructor
     * threads; other students see only their own private threads plus open ones.
     *
     * @return Builder<DiscussionThread>
     */
    public function visibleThreadsQuery(User $actor, DiscussionBoard $board): Builder
    {
        $board->loadMissing('offering');

        $query = DiscussionThread::query()
            ->where('board_id', $board->id)
            ->orderByDesc('pinned')
            ->latest('created_at');

        if ($board->offering && $this->access->isStaffOrAdmin($actor, $board->offering)) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($actor) {
            $inner->where('visibility', '!=', ThreadVisibility::PrivateToInstructor)
                ->orWhere('author_id', $actor->id);
        });
    }

    public function post(User $actor, DiscussionThread $thread, string $body, ?string $parentPostId = null, ?array $attachments = null): DiscussionPost
    {
        $this->authorize->authorize($actor, 'discussions.post');
        $this->access->assertCanAccessThread($actor, $thread);

        if ($thread->locked) {
            throw ValidationException::withMessages(['post' => [__('live.thread_locked')]]);
        }

        $post = DiscussionPost::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $actor->id,
            'parent_post_id' => $parentPostId,
            'body' => $body,
            'attachments' => $this->validatedStoredAttachments($actor, $attachments),
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
        $this->authorize->authorize($actor, 'discussions.moderate', $thread);

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
        $this->authorize->authorize($actor, 'discussions.grade', $thread);

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

    /**
     * @return \Illuminate\Support\Collection<int, DiscussionThread>
     */
    public function threadsForOffering(User $actor, CourseOffering $offering)
    {
        $this->authorize->authorize($actor, 'discussions.moderate', $offering);

        $board = $this->ensureBoard($offering);
        if ($board === null) {
            return collect();
        }

        return DiscussionThread::query()
            ->where('board_id', $board->id)
            ->orderByDesc('pinned')
            ->latest('created_at')
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function enrolledStudents(CourseOffering $offering)
    {
        return Enrollment::query()
            ->where('offering_id', $offering->id)
            ->where('status', EnrollmentStatus::Enrolled)
            ->with('student')
            ->orderBy('enrolled_at')
            ->get()
            ->pluck('student')
            ->filter()
            ->values();
    }

    public function storePostAttachment(User $actor, DiscussionThread $thread, UploadedFile $file): string
    {
        $this->authorize->authorize($actor, 'discussions.post');
        $this->access->assertCanAccessThread($actor, $thread);
        $this->assertAttachmentAllowed($file);

        $path = $this->storage->signedUploadPath(
            self::ATTACHMENT_PREFIX,
            (string) $actor->id,
            $file->getClientOriginalExtension() ?: $file->extension()
        );

        $realPath = $file->getRealPath() ?: $file->getPathname();
        $contents = is_string($realPath) && is_readable($realPath)
            ? (string) file_get_contents($realPath)
            : ($file->get() ?: '');
        $this->storage->store($path, $contents);

        return $path;
    }

    /**
     * @param  list<UploadedFile>  $files
     * @return list<array{path: string, name: string, size: int}>
     */
    public function storeUploadedAttachments(User $actor, DiscussionThread $thread, array $files): array
    {
        $stored = [];
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            $path = $this->storePostAttachment($actor, $thread, $file);
            $stored[] = [
                'path' => $path,
                'name' => $file->getClientOriginalName(),
                'size' => (int) $file->getSize(),
            ];
        }

        return $stored;
    }

    /**
     * @return array{path: string, name: string, size: int|null}
     */
    public function attachmentForDownload(User $actor, DiscussionPost $post, int $index): array
    {
        $post->loadMissing('thread');
        $thread = $post->thread;
        if ($thread === null) {
            abort(404);
        }

        $this->access->assertCanAccessThread($actor, $thread);

        $attachments = $post->attachments ?? [];
        if (! isset($attachments[$index]) || ! is_array($attachments[$index])) {
            abort(404);
        }

        $path = ltrim((string) ($attachments[$index]['path'] ?? ''), '/');
        if ($path === ''
            || str_contains($path, '..')
            || ! str_starts_with($path, self::ATTACHMENT_PREFIX.'/')
            || ! $this->storage->exists($path)) {
            abort(404);
        }

        $name = isset($attachments[$index]['name']) && is_string($attachments[$index]['name']) && $attachments[$index]['name'] !== ''
            ? $attachments[$index]['name']
            : basename($path);

        return [
            'path' => $path,
            'name' => $name,
            'size' => isset($attachments[$index]['size']) && is_numeric($attachments[$index]['size'])
                ? (int) $attachments[$index]['size']
                : null,
        ];
    }

    /**
     * Persist only objects we produced: [{path, name, size}]. Refuse URLs and foreign prefixes.
     *
     * @param  list<mixed>|null  $attachments
     * @return list<array{path: string, name: string, size: int|null}>|null
     */
    private function validatedStoredAttachments(User $actor, ?array $attachments): ?array
    {
        if ($attachments === null || $attachments === []) {
            return null;
        }

        $expectedPrefix = self::ATTACHMENT_PREFIX.'/'.$actor->id.'/';
        $normalized = [];

        foreach ($attachments as $item) {
            if (is_string($item)) {
                $this->rejectClientSuppliedUrl($item);
                $path = ltrim($item, '/');
                $name = basename($path);
                $size = null;
            } elseif (is_array($item)) {
                foreach (['file_url', 'url', 'href'] as $banned) {
                    if (isset($item[$banned])) {
                        throw ValidationException::withMessages([
                            'file_url' => [__('discussions.invalid_attachment')],
                        ]);
                    }
                }
                $path = isset($item['path']) && is_string($item['path']) ? ltrim($item['path'], '/') : '';
                $this->rejectClientSuppliedUrl($path);
                $name = isset($item['name']) && is_string($item['name']) && $item['name'] !== ''
                    ? $item['name']
                    : basename($path);
                $size = isset($item['size']) && is_numeric($item['size']) ? (int) $item['size'] : null;
            } else {
                throw ValidationException::withMessages([
                    'attachments' => [__('discussions.invalid_attachment')],
                ]);
            }

            if ($path === '' || str_contains($path, '..') || ! str_starts_with($path, $expectedPrefix)) {
                throw ValidationException::withMessages([
                    'attachments' => [__('discussions.invalid_attachment')],
                ]);
            }

            if (! $this->storage->exists($path)) {
                throw ValidationException::withMessages([
                    'attachments' => [__('discussions.invalid_attachment')],
                ]);
            }

            $normalized[] = [
                'path' => $path,
                'name' => $name,
                'size' => $size,
            ];
        }

        return $normalized;
    }

    private function rejectClientSuppliedUrl(string $value): void
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return;
        }

        if (str_contains($trimmed, '://') || str_starts_with($trimmed, '//') || str_starts_with(strtolower($trimmed), 'http')) {
            throw ValidationException::withMessages([
                'file_url' => [__('discussions.invalid_attachment')],
            ]);
        }
    }

    private function assertAttachmentAllowed(UploadedFile $file): void
    {
        $ext = strtolower((string) ($file->getClientOriginalExtension() ?: $file->extension() ?: ''));

        if ($ext === '' || ! in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                'attachments' => [__('discussions.file_type_not_allowed')],
            ]);
        }

        $mime = strtolower((string) $file->getMimeType());
        $expected = $this->mimesForExtension($ext);
        if ($expected !== []
            && $mime !== ''
            && $mime !== 'application/octet-stream'
            && ! in_array($mime, $expected, true)) {
            throw ValidationException::withMessages([
                'attachments' => [__('discussions.file_type_not_allowed')],
            ]);
        }

        if ($file->getSize() > 10 * 1024 * 1024) {
            throw ValidationException::withMessages([
                'attachments' => [__('discussions.invalid_attachment')],
            ]);
        }
    }

    /**
     * @return list<string>
     */
    private function mimesForExtension(string $ext): array
    {
        return match ($ext) {
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'png' => ['image/png'],
            'jpg', 'jpeg' => ['image/jpeg'],
            'gif' => ['image/gif'],
            'webp' => ['image/webp'],
            'txt' => ['text/plain'],
            default => [],
        };
    }
}
