<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\CollectsDiscussionUploads;
use App\Http\Controllers\Controller;
use App\Models\CourseOffering;
use App\Models\DiscussionPost;
use App\Models\DiscussionThread;
use App\Services\Discussions\DiscussionService;
use App\Services\Learning\OfferingAccessService;
use App\Services\Storage\ObjectStorageService;
use App\Support\Api\PaginatedEnvelope;
use App\Support\Api\StudentPayload;
use App\Support\Api\StudentRecordGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscussionController extends Controller
{
    use CollectsDiscussionUploads;

    public function __construct(
        private readonly StudentRecordGuard $guard,
        private readonly DiscussionService $discussions,
        private readonly OfferingAccessService $access,
        private readonly ObjectStorageService $storage,
    ) {}

    public function index(Request $request, CourseOffering $offering): JsonResponse
    {
        $this->guard->enrollmentForRead($request->user(), $offering);
        $board = $this->discussions->ensureBoard($offering);
        $perPage = PaginatedEnvelope::perPage($request->integer('per_page') ?: null);

        $query = $board === null
            ? DiscussionThread::query()->whereRaw('0 = 1')
            : $this->discussions->visibleThreadsQuery($request->user(), $board)->with('author');

        $page = $query->paginate($perPage);
        $page->setCollection($page->getCollection()->map(fn (DiscussionThread $thread) => $this->threadPayload($thread, false)));

        return response()->json(array_merge(PaginatedEnvelope::from($page), [
            'board' => $board === null ? null : [
                'id' => $board->id,
                'allow_student_threads' => $board->allow_student_threads,
            ],
        ]));
    }

    public function showThread(Request $request, DiscussionThread $thread): JsonResponse
    {
        $thread->loadMissing('board');
        $offering = CourseOffering::query()->findOrFail($thread->board->offering_id);
        $this->guard->enrollmentForRead($request->user(), $offering);
        $this->access->assertCanAccessThread($request->user(), $thread);

        $perPage = PaginatedEnvelope::perPage($request->integer('per_page') ?: null);
        $page = DiscussionPost::query()
            ->where('thread_id', $thread->id)
            ->with('author')
            ->orderBy('created_at')
            ->paginate($perPage);

        $page->setCollection($page->getCollection()->map(fn (DiscussionPost $post) => $this->postPayload($post)));

        return response()->json(array_merge(
            ['thread' => $this->threadPayload($thread, true)],
            PaginatedEnvelope::from($page),
        ));
    }

    public function storePost(Request $request, DiscussionThread $thread): JsonResponse
    {
        $thread->loadMissing('board');
        $offering = CourseOffering::query()->findOrFail($thread->board->offering_id);
        $this->guard->enrollmentForWrite($request->user(), $offering);
        $this->access->assertCanAccessThread($request->user(), $thread);
        $this->rejectPastedDiscussionFileUrl($request);

        $data = $request->validate([
            'body' => 'required|string',
            'parent_post_id' => 'nullable|exists:discussion_posts,id',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240',
            'attachment' => 'nullable|file|max:10240',
        ]);

        $stored = $this->discussions->storeUploadedAttachments(
            $request->user(),
            $thread,
            $this->uploadedDiscussionFiles($request),
        );

        $post = $this->discussions->post(
            $request->user(),
            $thread,
            $data['body'],
            $data['parent_post_id'] ?? null,
            $stored !== [] ? $stored : null,
        );

        return response()->json(['data' => $this->postPayload($post->load('author'))], 201);
    }

    public function storeThread(Request $request, CourseOffering $offering): JsonResponse
    {
        $this->guard->enrollmentForWrite($request->user(), $offering);
        $this->rejectPastedDiscussionFileUrl($request);

        $data = $request->validate([
            'title' => 'required|string|max:200',
            'body' => 'nullable|string',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240',
            'attachment' => 'nullable|file|max:10240',
        ]);

        unset($data['attachments'], $data['attachment']);

        $board = $this->discussions->provisionBoard($request->user(), $offering);
        $thread = $this->discussions->createThread(
            $request->user(),
            $board,
            $data,
            $this->uploadedDiscussionFiles($request),
        );

        return response()->json(['data' => $this->threadPayload($thread, true)], 201);
    }

    /** @return array<string, mixed> */
    private function threadPayload(DiscussionThread $thread, bool $detail): array
    {
        $data = [
            'id' => $thread->id,
            'title' => $thread->title,
            'pinned' => $thread->pinned,
            'locked' => $thread->locked,
            'author_id' => $thread->author_id,
            'created_at' => StudentPayload::iso($thread->created_at),
        ];

        if ($detail) {
            $data['is_graded'] = $thread->is_graded;
            $data['visibility'] = $thread->visibility->value;
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function postPayload(DiscussionPost $post): array
    {
        return [
            'id' => $post->id,
            'thread_id' => $post->thread_id,
            'author_id' => $post->author_id,
            'body' => $post->body,
            'parent_post_id' => $post->parent_post_id,
            'created_at' => StudentPayload::iso($post->created_at),
            'attachments' => $this->attachmentPayload($post),
        ];
    }

    /**
     * @return list<array{name: string, size: int|null, download_url: string}>
     */
    private function attachmentPayload(DiscussionPost $post): array
    {
        $out = [];
        foreach ($post->attachments ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $path = isset($item['path']) && is_string($item['path']) ? ltrim($item['path'], '/') : '';
            if ($path === '' || ! str_starts_with($path, 'discussion-attachments/')) {
                continue;
            }
            $out[] = [
                'name' => isset($item['name']) && is_string($item['name']) && $item['name'] !== ''
                    ? $item['name']
                    : basename($path),
                'size' => isset($item['size']) && is_numeric($item['size']) ? (int) $item['size'] : null,
                'download_url' => $this->storage->temporaryUrl($path),
            ];
        }

        return $out;
    }
}
