<?php

namespace App\Http\Controllers;

use App\Exceptions\AuthorizationException;
use App\Http\Controllers\Concerns\CollectsDiscussionUploads;
use App\Models\CourseOffering;
use App\Models\DiscussionPost;
use App\Models\DiscussionThread;
use App\Services\Discussions\DiscussionService;
use App\Services\Learning\OfferingAccessService;
use App\Services\Storage\ObjectStorageService;
use App\Services\Teach\TeachAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DiscussionController extends Controller
{
    use CollectsDiscussionUploads;

    public function showBoard(
        CourseOffering $offering,
        DiscussionService $discussions,
        OfferingAccessService $access,
        TeachAccessService $teachAccess,
    ): View {
        $access->assertCanAccessDiscussion(auth()->user(), $offering);
        $board = $discussions->ensureBoard($offering);

        return view('discussions.board', [
            'offering' => $offering->load('course'),
            'board' => $board,
            'canGrade' => $teachAccess->canGradeDiscussions(auth()->user(), $offering),
            'threads' => $board
                ? $discussions->visibleThreadsQuery(auth()->user(), $board)
                    ->with('author')
                    ->paginate(20)
                : null,
        ]);
    }

    public function storeThread(Request $request, CourseOffering $offering, DiscussionService $discussions, OfferingAccessService $access): RedirectResponse
    {
        $access->assertCanAccessDiscussion($request->user(), $offering);
        $this->rejectPastedDiscussionFileUrl($request);

        $data = $request->validate([
            'title' => 'required|string|max:200',
            'body' => 'nullable|string',
            'is_graded' => 'nullable|boolean',
            'participation_min_words' => 'nullable|integer|min:1',
            'participation_min_posts' => 'nullable|integer|min:1',
            'participation_min_replies' => 'nullable|integer|min:1',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240',
            'attachment' => 'nullable|file|max:10240',
        ]);

        unset($data['attachments'], $data['attachment']);

        $board = $discussions->provisionBoard($request->user(), $offering);
        $thread = $discussions->createThread(
            $request->user(),
            $board,
            $data,
            $this->uploadedDiscussionFiles($request),
        );

        return redirect()->route('discussions.thread', $thread)->with('status', __('live.thread_created'));
    }

    public function showThread(
        DiscussionThread $thread,
        OfferingAccessService $access,
        TeachAccessService $teachAccess,
        DiscussionService $discussions,
    ): View {
        $access->assertCanAccessThread(auth()->user(), $thread);

        $thread->load(['board.offering.course', 'grades.student']);
        $offering = $thread->board?->offering;
        $canGrade = $offering !== null && $teachAccess->canGradeDiscussions(auth()->user(), $thread);

        return view('discussions.thread', [
            'thread' => $thread,
            'offering' => $offering,
            'posts' => $thread->posts()->with('author')->orderBy('created_at')->paginate(20),
            'canGrade' => $canGrade,
            'students' => $canGrade && $offering !== null
                ? $discussions->enrolledStudents($offering)
                : collect(),
        ]);
    }

    public function storePost(Request $request, DiscussionThread $thread, DiscussionService $discussions, OfferingAccessService $access): RedirectResponse
    {
        $access->assertCanAccessThread($request->user(), $thread);
        $this->rejectPastedDiscussionFileUrl($request);

        $data = $request->validate([
            'body' => 'required|string',
            'parent_post_id' => 'nullable|exists:discussion_posts,id',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240',
            'attachment' => 'nullable|file|max:10240',
        ]);

        $stored = $discussions->storeUploadedAttachments(
            $request->user(),
            $thread,
            $this->uploadedDiscussionFiles($request),
        );

        $discussions->post(
            $request->user(),
            $thread,
            $data['body'],
            $data['parent_post_id'] ?? null,
            $stored !== [] ? $stored : null,
        );

        return back()->with('status', __('live.posted'));
    }

    public function downloadAttachment(
        Request $request,
        DiscussionPost $post,
        int $index,
        DiscussionService $discussions,
        ObjectStorageService $storage,
    ): StreamedResponse {
        try {
            $attachment = $discussions->attachmentForDownload($request->user(), $post, $index);
        } catch (AuthorizationException) {
            abort(403);
        } catch (ValidationException) {
            abort(403);
        }

        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $this->safeDownloadName($attachment['name']),
            $this->asciiDownloadName($attachment['name']),
        );

        return response()->stream(function () use ($storage, $attachment) {
            echo $storage->disk()->get($attachment['path']);
        }, Response::HTTP_OK, [
            'Content-Type' => $this->mimeForPath($attachment['path'], $attachment['name']),
            'Content-Disposition' => $disposition,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function safeDownloadName(string $name): string
    {
        $base = str_replace(["\0", '/', '\\', '%'], '-', $name);
        while (str_contains($base, '..')) {
            $base = str_replace('..', '', $base);
        }
        $base = trim($base, " \t.-");

        return $base !== '' ? $base : 'attachment';
    }

    private function asciiDownloadName(string $name): string
    {
        $ascii = preg_replace('/[^A-Za-z0-9._-]+/', '_', $this->safeDownloadName($name)) ?? 'attachment';
        $ascii = trim($ascii, '._-');

        return $ascii !== '' ? $ascii : 'attachment';
    }

    private function mimeForPath(string $path, string $name): string
    {
        $ext = strtolower((string) pathinfo($name !== '' ? $name : $path, PATHINFO_EXTENSION));

        return match ($ext) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'jpg', 'jpeg' => 'image/jpeg',
            'txt' => 'text/plain',
            default => 'application/octet-stream',
        };
    }
}
