<?php

use Codebyray\LivewireMediaUploader\MediaUploaderServiceProvider;
use Codebyray\LivewireMediaUploader\Tests\Browser\BrowserServiceProvider;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\Foundation\Application;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;

// Test-only router. Run through Playwright on loopback; never deploy it.
require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = Application::create(options: [
    'extra' => [
        'env' => ['APP_ENV' => 'testing'],
        'providers' => [
            LivewireServiceProvider::class,
            MediaLibraryServiceProvider::class,
            MediaUploaderServiceProvider::class,
            BrowserServiceProvider::class,
        ],
    ],
]);

$kernel = $app->make(Kernel::class);
$request = Request::capture();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
