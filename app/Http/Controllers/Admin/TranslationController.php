<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Translation;
use App\Services\Academics\TranslationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TranslationController extends Controller
{
    public function store(Request $request, TranslationService $service): RedirectResponse
    {
        $data = $request->validate([
            'entity_type' => 'required|string|max:64',
            'entity_id' => 'required|string|max:36',
            'field' => 'required|string|max:64',
            'locale' => 'required|in:ar,en,fr',
            'value' => 'required|string',
            'verified' => 'boolean',
        ]);

        $service->upsert(
            $request->user(),
            $data['entity_type'],
            $data['entity_id'],
            $data['field'],
            $data['locale'],
            $data['value'],
            (bool) ($data['verified'] ?? false)
        );

        return back()->with('status', __('academics.translation_saved'));
    }

    public function verify(Request $request, Translation $translation, TranslationService $service): RedirectResponse
    {
        $service->verify($request->user(), $translation);

        return back()->with('status', __('academics.translation_verified'));
    }

    public function requestAi(Request $request, TranslationService $service): RedirectResponse
    {
        $data = $request->validate([
            'entity_type' => 'required|string|max:64',
            'entity_id' => 'required|string|max:36',
            'field' => 'required|string|max:64',
            'source_locale' => 'required|in:ar,en,fr',
            'target_locale' => 'required|in:ar,en,fr',
            'source_text' => 'required|string',
        ]);

        $result = $service->requestAiTranslation(
            $request->user(),
            $data['entity_type'],
            $data['entity_id'],
            $data['field'],
            $data['source_locale'],
            $data['target_locale'],
            $data['source_text']
        );

        return back()->with(
            'status',
            $result ? __('academics.translation_ai_saved') : __('academics.translation_ai_skipped')
        );
    }
}
