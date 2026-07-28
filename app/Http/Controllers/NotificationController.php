<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('notifications.index', [
            'notifications' => Notification::query()
                ->where('user_id', $request->user()->id)
                ->where('channel', \App\Enums\NotificationChannel::InApp)
                ->latest('created_at')
                ->limit(50)
                ->get(),
        ]);
    }

    public function markRead(Request $request, Notification $notification, NotificationService $notifications): RedirectResponse
    {
        $notifications->markRead($request->user(), $notification);

        return back();
    }
}
