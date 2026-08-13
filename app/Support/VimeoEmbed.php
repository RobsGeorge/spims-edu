<?php

namespace App\Support;

final class VimeoEmbed
{
    public static function iframeUrl(string $vimeoId): string
    {
        $id = trim($vimeoId);

        return 'https://player.vimeo.com/video/'.rawurlencode($id);
    }
}
