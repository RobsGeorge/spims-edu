<?php

namespace App\Http\Controllers\Teach\Concerns;

use App\Models\CourseOffering;
use App\Services\Teach\TeachAccessService;
use Illuminate\Http\Request;

trait GuardsTeachOffering
{
    protected function guardTeach(Request $request, CourseOffering $offering): void
    {
        $user = $request->user();
        abort_unless(app(TeachAccessService::class)->canTeach($user), 403);
        app(TeachAccessService::class)->assertCanTeachOffering($user, $offering);
    }
}
