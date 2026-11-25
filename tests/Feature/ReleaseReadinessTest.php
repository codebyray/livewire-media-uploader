<?php

use Codebyray\LivewireMediaUploader\Livewire\MediaUploader;
use Codebyray\LivewireMediaUploader\Tests\Fixtures\JpegOnlyPost;
use Codebyray\LivewireMediaUploader\Tests\Fixtures\PrefixedFileNamer;
use Codebyray\LivewireMediaUploader\Tests\Fixtures\TestPost;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\Filesystem;
use Spatie\MediaLibrary\Support\FileNamer\DefaultFileNamer;

it('assigns distinct stored filenames to repeated uploads', function () {
    $post = TestPost::create(['title' => 'Repeated names']);
    Livewire::test(MediaUploader::class, ['for' => $post])
        ->set('uploads', [
            TemporaryUploadedFile::fake()->image('cover.png', 20, 20),
            TemporaryUploadedFile::fake()->image('cover.png', 30, 30),
            TemporaryUploadedFile::fake()->image('cover.png', 40, 40),
        ])
        ->call('uploadFiles')
        ->assertHasNoErrors();

    expect($post->fresh()->getMedia('images')->pluck('file_name')->all())
        ->toBe(['cover.png', 'cover-(1).png', 'cover-(2).png']);
});

it('uses normalized filenames for conflict handling with configured namers', function (string $strategy, int $count, string $namer) {
    $post = TestPost::create(['title' => 'Normalized names']);
    config()->set('media-library.file_namer', $namer);

    Livewire::test(MediaUploader::class, ['for' => $post, 'onNameConflict' => $strategy])
        ->set('uploads', [
            TemporaryUploadedFile::fake()->image('my photo.png', 20, 20),
            TemporaryUploadedFile::fake()->image('my photo.png', 30, 30),
            TemporaryUploadedFile::fake()->image('my photo.png', 40, 40),
        ])
        ->call('uploadFiles')
        ->assertHasNoErrors();

    $media = $post->fresh()->getMedia('images');
    expect($media)->toHaveCount($count)
        ->and($media->pluck('file_name')->unique())->toHaveCount($count);
    if ($namer === PrefixedFileNamer::class) {
        expect($media->first()->file_name)->toBe('asset-my-photo.png');
    }
})->with([
    'rename' => ['rename', 3, DefaultFileNamer::class],
    'skip' => ['skip', 1, DefaultFileNamer::class],
    'replace' => ['replace', 1, DefaultFileNamer::class],
    'custom rename' => ['rename', 3, PrefixedFileNamer::class],
    'custom skip' => ['skip', 1, PrefixedFileNamer::class],
    'custom replace' => ['replace', 1, PrefixedFileNamer::class],
]);

it('removes completed files and preserves unattempted files and metadata after a partial failure', function () {
    $post = JpegOnlyPost::create(['title' => 'Partial batch']);
    $component = Livewire::test(MediaUploader::class, ['for' => $post])
        ->set('uploads', [
            TemporaryUploadedFile::fake()->image('accepted.jpg', 20, 20),
            TemporaryUploadedFile::fake()->image('rejected.png', 30, 30),
            TemporaryUploadedFile::fake()->image('later.jpg', 40, 40),
        ])
        ->set('pendingMeta.2.caption', 'Keep this caption')
        ->call('uploadFiles')
        ->assertHasErrors('uploads.1')
        ->assertSet('selected.0.name', 'rejected.png')
        ->assertSet('selected.1.name', 'later.jpg')
        ->assertSet('pendingMeta.2.caption', 'Keep this caption')
        ->assertDispatched('media-upload-failed', collection: 'images', channel: null, queueKey: 1)
        ->assertNotDispatched('media-uploaded');

    expect($post->fresh()->getMedia('images'))->toHaveCount(1);

    $component->call('removeFromQueue', 1)
        ->assertHasNoErrors()
        ->call('uploadFiles')
        ->assertHasNoErrors()
        ->assertDispatched('media-uploaded')
        ->assertSet('uploads', []);

    $media = $post->fresh()->getMedia('images');
    expect($media->pluck('file_name')->all())->toBe(['accepted.jpg', 'later.jpg'])
        ->and($media->last()->getCustomProperty('caption'))->toBe('Keep this caption');
});

it('can retry a transient failure without repeating successful files', function () {
    $post = TestPost::create(['title' => 'Transient failure']);
    $realFilesystem = app(Filesystem::class);
    $filesystem = Mockery::mock(Filesystem::class)->makePartial();
    $filesystem->shouldReceive('add')->once()->ordered()->andReturnUsing(fn (...$args) => $realFilesystem->add(...$args));
    $filesystem->shouldReceive('add')->once()->ordered()->andReturn(false);
    $filesystem->shouldReceive('add')->once()->ordered()->andReturnUsing(fn (...$args) => $realFilesystem->add(...$args));
    app()->instance(Filesystem::class, $filesystem);

    $component = Livewire::test(MediaUploader::class, ['for' => $post])
        ->set('uploads', [
            TemporaryUploadedFile::fake()->image('one.png', 20, 20),
            TemporaryUploadedFile::fake()->image('two.png', 30, 30),
        ])
        ->call('uploadFiles')
        ->assertHasErrors('uploads.1')
        ->assertSet('selected.0.name', 'two.png');

    $component->call('uploadFiles')->assertHasNoErrors()->assertSet('uploads', []);
    expect($post->fresh()->getMedia('images')->pluck('file_name')->all())->toBe(['one.png', 'two.png']);
});

it('does not emit deferred completion until the remaining files succeed', function () {
    $post = JpegOnlyPost::create(['title' => 'Deferred retry']);
    Gate::define('manage-media', fn (?User $user) => true);

    $component = Livewire::test(MediaUploader::class, [
        'model' => JpegOnlyPost::class,
        'authorizeAbility' => 'manage-media',
        'channel' => 'gallery',
    ])
        ->set('uploads', [
            TemporaryUploadedFile::fake()->image('one.jpg', 20, 20),
            TemporaryUploadedFile::fake()->image('rejected.png', 30, 30),
            TemporaryUploadedFile::fake()->image('two.jpg', 40, 40),
        ])
        ->call('attachTo', JpegOnlyPost::class, $post->id, 'images', null, 'gallery')
        ->assertHasErrors('uploads.1')
        ->assertNotDispatched('media-attached');

    $component->call('removeFromQueue', 1)->call('uploadFiles')
        ->assertDispatched('media-attached', model: JpegOnlyPost::class, id: (string) $post->id, channel: 'gallery')
        ->assertSet('pendingModelClass', null);

    $component->call('attachTo', JpegOnlyPost::class, $post->id, 'images', null, 'gallery')
        ->assertNotDispatched('media-attached');
    expect($post->fresh()->getMedia('images'))->toHaveCount(2);
});

it('clears stale upload errors when clearing or reordering the queue', function () {
    $post = JpegOnlyPost::create(['title' => 'Queue errors']);
    $component = Livewire::test(MediaUploader::class, ['for' => $post])
        ->set('uploads', [
            TemporaryUploadedFile::fake()->image('bad.png', 20, 20),
            TemporaryUploadedFile::fake()->image('later.jpg', 30, 30),
        ])
        ->call('uploadFiles')->assertHasErrors('uploads.0')
        ->call('reorderQueue', 1, 0)->assertHasNoErrors();

    $component->call('clearQueue')->assertHasNoErrors()->assertSet('uploads', []);
});

it('renders upload failure messages in both themes', function (string $theme) {
    $post = JpegOnlyPost::create(['title' => 'Visible failures']);
    Livewire::test(MediaUploader::class, ['for' => $post, 'theme' => $theme])
        ->set('uploads', [TemporaryUploadedFile::fake()->image('rejected.png', 20, 20)])
        ->call('uploadFiles')
        ->assertHasErrors('uploads.0')
        ->assertSee('This file could not be uploaded.')
        ->assertSee('Upload paused.');
})->with(['tailwind', 'bootstrap']);

it('cleans up an interrupted storage attempt before retrying', function () {
    $post = TestPost::create(['title' => 'Interrupted conversion']);
    $realFilesystem = app(Filesystem::class);
    $filesystem = Mockery::mock(Filesystem::class)->makePartial();
    $filesystem->shouldReceive('add')->once()->ordered()->andReturnUsing(function (...$args) use ($realFilesystem) {
        $realFilesystem->add(...$args);
        throw new RuntimeException('Simulated synchronous conversion failure');
    });
    $filesystem->shouldReceive('add')->once()->ordered()->andReturnUsing(fn (...$args) => $realFilesystem->add(...$args));
    app()->instance(Filesystem::class, $filesystem);

    $component = Livewire::test(MediaUploader::class, ['for' => $post])
        ->set('uploads', [TemporaryUploadedFile::fake()->image('cover.png', 20, 20)])
        ->call('uploadFiles')->assertHasErrors('uploads.0');

    expect($post->fresh()->getMedia('images'))->toHaveCount(0)
        ->and(Storage::disk('public')->allFiles())->toHaveCount(0);

    $component->call('uploadFiles')->assertHasNoErrors();
    expect($post->fresh()->getMedia('images')->pluck('file_name')->all())->toBe(['cover.png']);
});

it('fails safely when a custom namer cannot generate distinct names', function () {
    $namer = new class extends DefaultFileNamer
    {
        public function originalFileName(string $fileName): string
        {
            return 'constant';
        }
    };
    config()->set('media-library.file_namer', $namer::class);
    $post = TestPost::create(['title' => 'Constant filename']);

    Livewire::test(MediaUploader::class, ['for' => $post])
        ->set('uploads', [
            TemporaryUploadedFile::fake()->image('one.png', 20, 20),
            TemporaryUploadedFile::fake()->image('two.png', 30, 30),
        ])
        ->call('uploadFiles')->assertHasErrors('uploads.1');

    expect($post->fresh()->getMedia('images'))->toHaveCount(1);
});

it('does not requeue a saved replacement when old-file cleanup fails', function () {
    $post = TestPost::create(['title' => 'Cleanup failure']);
    Livewire::test(MediaUploader::class, ['for' => $post])
        ->set('uploads', [TemporaryUploadedFile::fake()->image('cover.png', 20, 20)])
        ->call('uploadFiles');
    $originalId = $post->fresh()->getFirstMedia('images')->id;
    $realFilesystem = app(Filesystem::class);
    $filesystem = Mockery::mock(Filesystem::class)->makePartial();
    $filesystem->shouldReceive('add')->once()->andReturnUsing(fn (...$args) => $realFilesystem->add(...$args));
    $filesystem->shouldReceive('removeAllFiles')->once()->andThrow(new RuntimeException('Simulated cleanup failure'));
    app()->instance(Filesystem::class, $filesystem);

    Livewire::test(MediaUploader::class, ['for' => $post, 'onNameConflict' => 'replace'])
        ->set('uploads', [TemporaryUploadedFile::fake()->image('cover.png', 30, 30)])
        ->call('uploadFiles')
        ->assertHasErrors('uploads')
        ->assertSet('uploads', [])
        ->assertSee('Please contact the site administrator')
        ->assertDontSee('Simulated cleanup failure')
        ->assertNotDispatched('media-uploaded');

    expect($post->fresh()->getFirstMedia('images')->id)->not->toBe($originalId)
        ->and(is_file($post->fresh()->getFirstMedia('images')->getPath()))->toBeTrue();
});

it('round trips temporary uploads on a non-default disk', function () {
    config()->set('livewire.temporary_file_upload.disk', 'tmp-for-tests');
    $post = TestPost::create(['title' => 'Separate temporary storage']);

    Livewire::test(MediaUploader::class, ['for' => $post, 'skipExactDuplicates' => true])
        ->set('uploads', [TemporaryUploadedFile::fake()->image('remote.png', 30, 30)])
        ->call('uploadFiles')->assertHasNoErrors();

    $media = $post->fresh()->getFirstMedia('images');
    expect(is_file($media->getPath()))->toBeTrue()
        ->and($media->getCustomProperty('sha256'))->toBe(hash_file('sha256', $media->getPath()));
});

it('applies percentage sizing padding transparency and opacity from the shipped watermark defaults', function (int $size) {
    $defaults = require __DIR__.'/../../config/media-uploader.php';
    $watermarkPath = tempnam(sys_get_temp_dir(), 'release-watermark-');
    $watermark = imagecreatetruecolor(10, 10);
    imagealphablending($watermark, false);
    imagesavealpha($watermark, true);
    imagefill($watermark, 0, 0, imagecolorallocatealpha($watermark, 0, 0, 0, 127));
    imagefilledrectangle($watermark, 0, 0, 4, 9, imagecolorallocatealpha($watermark, 255, 0, 0, 0));
    imagepng($watermark, $watermarkPath);
    imagedestroy($watermark);
    config()->set('media-uploader.watermark', [...$defaults['watermark'], 'enabled' => true, 'path' => $watermarkPath]);
    $post = TestPost::create(['title' => 'Shipped defaults']);

    try {
        Livewire::test(MediaUploader::class, ['for' => $post])
            ->set('uploads', [TemporaryUploadedFile::fake()->image('defaults.png', $size, $size)])
            ->call('uploadFiles')->assertHasNoErrors();

        $stored = imagecreatefrompng($post->fresh()->getFirstMedia('images')->getPath());
        $width = (int) ($size * 0.2);
        $start = $size - 24 - $width;
        $red = imagecolorsforindex($stored, imagecolorat($stored, $start + 4, $start + 4));
        $transparent = imagecolorsforindex($stored, imagecolorat($stored, $size - 28, $start + 4));
        $padding = imagecolorsforindex($stored, imagecolorat($stored, $size - 1, $size - 1));

        expect($red['red'])->toBeGreaterThan(150)->toBeLessThan(210)
            ->and($transparent['red'])->toBe(0)
            ->and($padding['red'])->toBe(0);
        imagedestroy($stored);
    } finally {
        unlink($watermarkPath);
    }
})->with([200, 400]);
