<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        config(['server_sync.env_path' => storage_path('framework/testing/server-sync.env')]);
        File::deleteDirectory(storage_path('framework/testing/server-sync'));
    }
}
