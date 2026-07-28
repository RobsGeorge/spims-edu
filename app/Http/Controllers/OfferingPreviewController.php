<?php

namespace App\Http\Controllers;

use App\Models\CourseOffering;
use App\Services\Offerings\OfferingService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class OfferingPreviewController extends Controller
{
    public function show(CourseOffering $offering, OfferingService $service): View
    {
        return view('offerings.preview', [
            'preview' => $service->previewPayload($offering->load(['weeks.items', 'course'])),
            'offering' => $offering,
        ]);
    }

    public function json(CourseOffering $offering, OfferingService $service): JsonResponse
    {
        return response()->json($service->previewPayload($offering->load(['weeks.items', 'course'])));
    }

    public function pricing(CourseOffering $offering): JsonResponse
    {
        $offering->load('course');

        return response()->json([
            'usd_minor' => $offering->resolvedPriceUsd(),
            'egp_minor' => $offering->resolvedPriceEgp(),
            'for_eg' => $offering->resolvedPriceForCountry('EG'),
            'for_us' => $offering->resolvedPriceForCountry('US'),
            'is_free' => $offering->course->is_free,
        ]);
    }
}
