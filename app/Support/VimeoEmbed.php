<?php

namespace App\Support;

use App\Enums\VideoProvider;
use App\Support\Content\VideoUrlParser;

final class VimeoEmbed
{
    public static function iframeUrl(string $vimeoId): string
    {
        return VideoUrlParser::iframeUrl(VideoProvider::Vimeo, trim($vimeoId));
    }
}
