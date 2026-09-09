<?php

namespace App\Http\Controllers;

use App\Services\SystemDocs\SystemDocsCatalog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SystemDocsController extends Controller
{
    public function __construct(
        private readonly SystemDocsCatalog $catalog,
    ) {}

    public function index(Request $request): View
    {
        $viewer = $request->user();
        abort_unless($this->catalog->canBrowse($viewer), 404);

        $audience = $request->string('audience')->trim()->toString();
        $audienceFilter = in_array($audience, ['client', 'technical'], true) ? $audience : null;

        return view('system-docs.index', [
            'pages' => $this->catalog->pagesVisibleTo($viewer, $audienceFilter),
            'audienceFilter' => $audienceFilter,
            'guestPublished' => $this->catalog->isGuestPublished(),
            'isGuest' => $viewer === null,
        ]);
    }

    public function show(Request $request, string $slug): View
    {
        $viewer = $request->user();
        abort_unless($this->catalog->canBrowse($viewer), 404);

        $page = $this->catalog->findPage($slug, $viewer);
        abort_if($page === null, 404);

        $locale = app()->getLocale();
        $body = $this->catalog->renderBody($slug, $locale);
        $siblings = $this->catalog->pagesVisibleTo($viewer, $page['audience']);

        return view('system-docs.show', [
            'page' => $page,
            'bodyHtml' => $body['html'],
            'usedFallback' => $body['used_fallback'],
            'siblings' => $siblings,
            'isGuest' => $viewer === null,
        ]);
    }
}
