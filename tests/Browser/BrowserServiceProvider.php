<?php

namespace Codebyray\LivewireMediaUploader\Tests\Browser;

use Codebyray\LivewireMediaUploader\Tests\Fixtures\TestPost;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class BrowserServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $runtime = __DIR__.'/.runtime';
        foreach (['', '/sessions', '/views', '/local', '/public'] as $directory) {
            if (! is_dir($runtime.$directory)) {
                mkdir($runtime.$directory, 0755, true);
            }
        }
        if (! is_file($runtime.'/database.sqlite')) {
            touch($runtime.'/database.sqlite');
        }

        config()->set([
            'app.key' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
            'app.url' => 'http://127.0.0.1:8123',
            'app.debug' => true,
            'database.default' => 'browser',
            'database.connections.browser' => ['driver' => 'sqlite', 'database' => $runtime.'/database.sqlite', 'prefix' => ''],
            'session.driver' => 'file',
            'session.files' => $runtime.'/sessions',
            'cache.default' => 'array',
            'queue.default' => 'sync',
            'view.compiled' => $runtime.'/views',
            'filesystems.disks.local' => ['driver' => 'local', 'root' => $runtime.'/local'],
            'filesystems.disks.public' => ['driver' => 'local', 'root' => $runtime.'/public', 'url' => '/storage', 'visibility' => 'public'],
            'media-library.disk_name' => 'public',
            'media-library.image_driver' => 'gd',
            'livewire.temporary_file_upload.disk' => 'local',
        ]);
    }

    public function boot(): void
    {
        if (! Schema::hasTable('posts')) {
            (require dirname(__DIR__).'/database/migrations/0001_01_01_000000_create_posts_table.php')->up();
            (require dirname(__DIR__).'/database/migrations/0001_01_01_000000_create_media_table.php')->up();
        }
        $this->loadViewsFrom(__DIR__.'/views', 'uploader-browser');
        Livewire::component('browser-create', CreateForm::class);
        Gate::define('manage-media', fn (?User $user) => true);

        Route::get('/health', fn () => response('OK'));
        Route::get('/assets/tailwind.css', fn () => response()->file(__DIR__.'/.runtime/tailwind.css', ['Content-Type' => 'text/css']));
        Route::get('/assets/bootstrap.css', fn () => response()->file(dirname(__DIR__, 2).'/node_modules/bootstrap/dist/css/bootstrap.min.css', ['Content-Type' => 'text/css']));
        Route::get('/storage/{path}', fn (string $path) => Storage::disk('public')->response($path))->where('path', '.*');
        Route::middleware('web')->get('/{theme}', function (string $theme) {
            return view('uploader-browser::app', [
                'theme' => $theme,
                'left' => TestPost::create(['title' => 'Left gallery']),
                'right' => TestPost::create(['title' => 'Right gallery']),
            ]);
        })->where('theme', 'tailwind|bootstrap');
    }
}
