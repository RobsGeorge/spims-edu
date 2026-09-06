<?php

namespace App\Services\Pdf;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Thin wrapper around barryvdh/laravel-dompdf (a pure-PHP renderer, no system
 * binary dependency) so PDF rendering can fail softly. Per CLAUDE.md rule 7
 * ("mailers optional in dev — never blocks build"), generalized here to PDF
 * rendering: any renderer failure (missing extension, memory limit, malformed
 * markup, ...) is logged and swallowed, letting the caller fall back to
 * storing the same content as HTML instead.
 */
class PdfRenderService
{
    public function renderPdf(string $html): ?string
    {
        try {
            $pdf = app('dompdf.wrapper');
            $pdf->loadHTML($html);

            return $pdf->output();
        } catch (Throwable $e) {
            Log::warning('pdf.render_failed', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
