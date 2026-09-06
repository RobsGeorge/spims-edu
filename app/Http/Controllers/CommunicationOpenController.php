<?php

namespace App\Http\Controllers;

use App\Models\CommunicationLog;
use App\Services\Communications\CommunicationLogWriter;
use Illuminate\Http\Response;

class CommunicationOpenController extends Controller
{
    public function __invoke(CommunicationLog $log, CommunicationLogWriter $writer): Response
    {
        $writer->markOpened($log);

        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        return response($gif, 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }
}
