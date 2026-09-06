<?php

namespace App\Http\Controllers;

use App\Models\NotificationReminder;
use App\Services\Communications\NotificationPreferenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationSettingsController extends Controller
{
    public function __construct(
        private readonly NotificationPreferenceService $preferences,
    ) {}

    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('settings.notifications', [
            'user' => $user,
            'matrix' => $this->preferences->matrix($user, $user),
            'reminders' => $this->preferences->upcoming($user),
            'eventKeys' => NotificationPreferenceService::EVENT_KEYS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*.event_key' => ['required', 'string', 'max:80'],
            'preferences.*.channel' => ['required', 'in:in_app,mail,whatsapp'],
            'preferences.*.enabled' => ['nullable', 'boolean'],
        ]);

        $rows = [];
        foreach ($data['preferences'] as $row) {
            $rows[] = [
                'event_key' => $row['event_key'],
                'channel' => $row['channel'],
                'enabled' => (bool) ($row['enabled'] ?? false),
            ];
        }

        $this->preferences->put($request->user(), $request->user(), $rows);

        return back()->with('status', __('communications.preferences_saved'));
    }

    public function storeReminder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject_type' => ['required', 'string', 'max:80'],
            'subject_id' => ['required', 'string', 'max:40'],
            'remind_at' => ['required', 'date'],
        ]);

        $this->preferences->schedule(
            $request->user(),
            $request->user(),
            $data['subject_type'],
            $data['subject_id'],
            new \DateTimeImmutable($data['remind_at']),
        );

        return back()->with('status', __('communications.reminder_scheduled'));
    }

    public function cancelReminder(Request $request, NotificationReminder $reminder): RedirectResponse
    {
        $this->preferences->cancel($request->user(), $reminder);

        return back()->with('status', __('communications.reminder_cancelled'));
    }
}
