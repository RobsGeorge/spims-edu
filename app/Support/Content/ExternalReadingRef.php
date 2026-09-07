<?php

namespace App\Support\Content;

final class ExternalReadingRef
{
    public function __construct(
        public readonly string $canonicalUrl,
        public readonly bool $embeddable,
        public readonly string $hostKind,
    ) {}
}
