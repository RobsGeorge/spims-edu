<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

trait CollectsDiscussionUploads
{
    protected function rejectPastedDiscussionFileUrl(Request $request): void
    {
        if ($request->exists('file_url')) {
            throw ValidationException::withMessages([
                'file_url' => [__('discussions.invalid_attachment')],
            ]);
        }

        if ($request->exists('attachments') && ! $request->hasFile('attachments') && ! $request->hasFile('attachment')) {
            $raw = $request->input('attachments');
            if (is_array($raw) && $raw !== []) {
                throw ValidationException::withMessages([
                    'attachments' => [__('discussions.invalid_attachment')],
                ]);
            }
        }
    }

    /**
     * @return list<UploadedFile>
     */
    protected function uploadedDiscussionFiles(Request $request): array
    {
        $files = [];

        if ($request->hasFile('attachments')) {
            $uploaded = $request->file('attachments');
            if ($uploaded instanceof UploadedFile) {
                $files[] = $uploaded;
            } elseif (is_array($uploaded)) {
                foreach ($uploaded as $file) {
                    if ($file instanceof UploadedFile) {
                        $files[] = $file;
                    }
                }
            }
        }

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            if ($file instanceof UploadedFile) {
                $files[] = $file;
            }
        }

        return $files;
    }
}
