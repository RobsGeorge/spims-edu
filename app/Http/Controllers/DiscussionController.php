<?php

namespace App\Http\Controllers;

use App\Models\CourseOffering;
use App\Models\DiscussionThread;
use App\Services\Discussions\DiscussionService;
use App\Services\Learning\OfferingAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DiscussionController extends Controller
{
    public function showBoard(CourseOffering $offering, DiscussionService $discussions, OfferingAccessService $access): View
    {
        $access->assertCanAccessDiscussion(auth()->user(), $offering);
        $board = $discussions->ensureBoard($offering);

        return view('discussions.board', [
            'offering' => $offering->load('course'),
            'board' => $board,
            'threads' => DiscussionThread::query()
                ->where('board_id', $board->id)
                ->orderByDesc('pinned')
                ->latest('created_at')
                ->with('author')
                ->get(),
        ]);
    }

    public function storeThread(Request $request, CourseOffering $offering, DiscussionService $discussions, OfferingAccessService $access): RedirectResponse
    {
        $access->assertCanAccessDiscussion($request->user(), $offering);

        $data = $request->validate([
            'title' => 'required|string|max:200',
            'body' => 'nullable|string',
            'is_graded' => 'nullable|boolean',
            'participation_min_words' => 'nullable|integer|min:1',
            'participation_min_posts' => 'nullable|integer|min:1',
            'participation_min_replies' => 'nullable|integer|min:1',
        ]);

        $board = $discussions->ensureBoard($offering);
        $thread = $discussions->createThread($request->user(), $board, $data);

        return redirect()->route('discussions.thread', $thread)->with('status', __('live.thread_created'));
    }

    public function showThread(DiscussionThread $thread, OfferingAccessService $access): View
    {
        $access->assertCanAccessThread(auth()->user(), $thread);

        return view('discussions.thread', [
            'thread' => $thread->load(['board.offering.course', 'posts.author', 'grades']),
        ]);
    }

    public function storePost(Request $request, DiscussionThread $thread, DiscussionService $discussions, OfferingAccessService $access): RedirectResponse
    {
        $access->assertCanAccessThread($request->user(), $thread);

        $data = $request->validate([
            'body' => 'required|string',
            'parent_post_id' => 'nullable|exists:discussion_posts,id',
        ]);

        $discussions->post(
            $request->user(),
            $thread,
            $data['body'],
            $data['parent_post_id'] ?? null
        );

        return back()->with('status', __('live.posted'));
    }
}
