<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\SuperAdmin\IntegrationSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IntegrationSettingsController extends Controller
{
    public function index(Request $request, IntegrationSettingsService $integrations): View
    {
        $integrations->authorize($request->user());

        return view('superadmin.integrations.index', $integrations->catalog());
    }

    public function update(Request $request, IntegrationSettingsService $integrations): RedirectResponse
    {
        $data = $request->validate([
            'safe' => 'nullable|array',
            'secrets' => 'nullable|array',
            'clear' => 'nullable|array',
        ]);

        $integrations->update(
            $request->user(),
            is_array($data['safe'] ?? null) ? $data['safe'] : [],
            is_array($data['secrets'] ?? null) ? $data['secrets'] : [],
            is_array($data['clear'] ?? null) ? $data['clear'] : []
        );

        return back()->with('status', __('integrations.updated'));
    }

    public function testMail(Request $request, IntegrationSettingsService $integrations): RedirectResponse
    {
        $data = $request->validate([
            'identity' => 'required|string',
        ]);

        $integrations->sendTestMail($request->user(), $data['identity']);

        return back()->with('status', __('integrations.test_mail_sent', [
            'email' => $request->user()->email,
        ]));
    }

    public function testPayment(Request $request, IntegrationSettingsService $integrations): RedirectResponse
    {
        $data = $request->validate([
            'gateway' => 'required|string',
            'confirm' => 'sometimes|boolean',
        ]);

        $result = $integrations->sendTestPayment(
            $request->user(),
            $data['gateway'],
            $request->boolean('confirm')
        );

        $key = $result['simulated'] ? 'integrations.test_payment_simulated' : 'integrations.test_payment_live';

        return back()->with('status', __($key, [
            'gateway' => $result['gateway'] ?? $data['gateway'],
            'reference' => $result['reference'],
            'amount' => (string) $result['amount_minor'],
            'currency' => $result['currency'],
        ]));
    }
}
