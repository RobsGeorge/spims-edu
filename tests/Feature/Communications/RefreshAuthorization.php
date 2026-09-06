<?php

namespace Tests\Feature\Communications;

use App\Support\AuthorizeService;

trait RefreshAuthorization
{
    protected function setUp(): void
    {
        parent::setUp();
        app(AuthorizeService::class)->forgetMatrixCache();
    }
}
