<?php

namespace App\Support\Content;

use App\Enums\VideoProvider;

final class VideoRef
{
    public function __construct(
        public readonly VideoProvider $provider,
        public readonly string $id,
    ) {}
}
