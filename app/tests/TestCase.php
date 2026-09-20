<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;

abstract class TestCase extends BaseTestCase
{
    private string $isolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolatedStorage = sys_get_temp_dir().'/md-notes-test-storage-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($this->isolatedStorage.'/framework/views');
        File::ensureDirectoryExists($this->isolatedStorage.'/logs');
        $this->app->useStoragePath($this->isolatedStorage);
        config([
            'view.compiled' => $this->isolatedStorage.'/framework/views',
            'logging.channels.single.path' => $this->isolatedStorage.'/logs/laravel.log',
            'logging.channels.daily.path' => $this->isolatedStorage.'/logs/laravel.log',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->isolatedStorage);
        parent::tearDown();
    }
}
