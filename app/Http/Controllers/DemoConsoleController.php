<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Demo\DemoConsoleService;
use App\Support\ConfirmationToken;
use App\Support\DemoPersonas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DemoConsoleController extends Controller
{
    public function show(ConfirmationToken $confirm): View
    {
        $catalog = DemoPersonas::all();
        $emails = array_column($catalog, 'email');
        $existing = User::query()
            ->whereIn('email', $emails)
            ->pluck('email')
            ->all();

        $personas = array_map(function (array $persona) use ($existing) {
            $persona['ready'] = in_array($persona['email'], $existing, true);
            $persona['name'] = trim($persona['first_name'].' '.$persona['last_name']);
            $persona['role_label'] = $this->roleLabel($persona['roles']);
            $persona['blurb'] = __('demo.blurb_'.$persona['slug']);

            return $persona;
        }, $catalog);

        return view('demo.show', [
            'featured' => array_values(array_filter($personas, fn (array $p) => $p['featured'])),
            'more' => array_values(array_filter($personas, fn (array $p) => ! $p['featured'])),
            'ready' => in_array('student1@spims.test', $existing, true),
            'seedToken' => $confirm->issue('demo.seed'),
            'resetToken' => $confirm->issue('demo.reset'),
        ]);
    }

    public function seed(Request $request, ConfirmationToken $confirm, DemoConsoleService $demo): RedirectResponse
    {
        $confirm->consume('demo.seed', $request->input('confirmation_token'));
        $demo->refresh($request->user());

        return redirect()->route('demo.show')->with('status', __('demo.seeded'));
    }

    public function reset(Request $request, ConfirmationToken $confirm, DemoConsoleService $demo): RedirectResponse
    {
        $confirm->consume('demo.reset', $request->input('confirmation_token'));
        $demo->refresh($request->user());

        return redirect()->route('demo.show')->with('status', __('demo.reset_done'));
    }

    public function enter(Request $request, string $persona, DemoConsoleService $demo): RedirectResponse
    {
        $catalog = DemoPersonas::find($persona);
        abort_unless($catalog !== null, 404);

        $user = $demo->enter($persona, $request->user());
        $locale = in_array($user->preferred_locale, ['ar', 'en', 'fr'], true)
            ? $user->preferred_locale
            : 'en';

        return redirect()
            ->to(DemoPersonas::landingUrl($catalog))
            ->withCookie(cookie('locale', $locale, 60 * 24 * 365));
    }

    /**
     * @param  list<string>  $roles
     */
    private function roleLabel(array $roles): string
    {
        if ($roles === ['INSTRUCTOR', 'STUDENT']) {
            return __('demo.role_dual');
        }

        return collect($roles)
            ->map(fn (string $role) => __('roles_hub.role_'.$role))
            ->implode(' · ');
    }
}
