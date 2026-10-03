<?php

use Codebyray\LivewireMediaUploader\Enums\NameConflictStrategy;
use Codebyray\LivewireMediaUploader\Exceptions\ModelResolutionException;
use Codebyray\LivewireMediaUploader\Livewire\MediaUploader;
use Codebyray\LivewireMediaUploader\Tests\Fixtures\TestableMediaUploader;
use Codebyray\LivewireMediaUploader\Tests\Fixtures\TestPost;
use Illuminate\Foundation\Auth\User;
use Illuminate\View\ViewException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

it('renders the component', function () {
    $post = TestPost::create(['title' => 'Hello']);

    Livewire::test(MediaUploader::class, [
        'for' => $post,
        'collection' => 'images',
    ])->assertSee('Manage gallery');
});

it('uploads a single image and lists it', function () {
    $post = TestPost::create(['title' => 'Hello']);
    $file = TemporaryUploadedFile::fake()->image('one.jpg', 100, 100)->size(200); // KB

    Livewire::test(MediaUploader::class, [
        'for' => $post,
        'collection' => 'images',
        'preset' => 'images',
    ])
        ->set('uploads', [$file])
        ->set('pendingMeta.0.caption', 'Cover')
        ->set('pendingMeta.0.description', 'Hero image')
        ->set('pendingMeta.0.order', 1)
        ->call('uploadFiles')
        ->assertDispatched('media-uploaded');

    expect($post->getMedia('images'))->toHaveCount(1);
    $media = $post->getFirstMedia('images');

    expect($media->getCustomProperty('caption'))->toBe('Cover');
    expect($media->getCustomProperty('description'))->toBe('Hero image');
    expect($media->order_column)->toBe(1);
});

it('applies the configured watermark to uploaded images', function () {
    $post = TestPost::create(['title' => 'Hello']);
    $file = TemporaryUploadedFile::fake()->image('watermarked.png', 100, 100);
    $watermarkPath = tempnam(sys_get_temp_dir(), 'media-watermark-');

    $watermark = imagecreatetruecolor(10, 10);
    imagefill($watermark, 0, 0, imagecolorallocate($watermark, 255, 0, 0));
    imagepng($watermark, $watermarkPath);
    imagedestroy($watermark);

    config()->set('media-uploader.watermark.path', $watermarkPath);

    Livewire::test(MediaUploader::class, [
        'for' => $post,
        'collection' => 'images',
        'preset' => 'images',
        'watermark' => true,
    ])
        ->set('uploads', [$file])
        ->call('uploadFiles')
        ->assertDispatched('media-uploaded');

    $storedImage = imagecreatefrompng($post->getFirstMedia('images')->getPath());
    $bottomRightPixel = imagecolorsforindex($storedImage, imagecolorat($storedImage, 99, 99));

    expect($bottomRightPixel['red'])->toBeGreaterThan(240)
        ->and($bottomRightPixel['green'])->toBeLessThan(15)
        ->and($bottomRightPixel['blue'])->toBeLessThan(15);

    imagedestroy($storedImage);
    unlink($watermarkPath);
});

it('leaves uploaded images unchanged when watermarking is disabled', function () {
    $post = TestPost::create(['title' => 'Hello']);
    $file = TemporaryUploadedFile::fake()->image('plain.png', 100, 100);
    $originalHash = hash_file('sha256', $file->getRealPath());

    Livewire::test(MediaUploader::class, [
        'for' => $post,
        'collection' => 'images',
        'preset' => 'images',
    ])
        ->set('uploads', [$file])
        ->call('uploadFiles');

    expect(hash_file('sha256', $post->getFirstMedia('images')->getPath()))->toBe($originalHash);
});

it('leaves non-image uploads untouched when watermarking is enabled', function () {
    $post = TestPost::create(['title' => 'Hello']);
    $file = TemporaryUploadedFile::fake()->create('manual.pdf', 10, 'application/pdf');

    Livewire::test(MediaUploader::class, [
        'for' => $post,
        'collection' => 'attachments',
        'preset' => 'docs',
        'watermark' => true,
    ])
        ->set('uploads', [$file])
        ->call('uploadFiles')
        ->assertDispatched('media-uploaded');

    expect($post->getFirstMedia('attachments')->file_name)->toBe('manual.pdf');
});

it('uses the configured preset size unless the component overrides it', function () {
    $post = TestPost::create(['title' => 'Hello']);
    config()->set('media-uploader.presets.images.max_kb', 321);

    Livewire::test(MediaUploader::class, [
        'for' => $post,
        'collection' => 'images',
        'preset' => 'images',
    ])->assertSet('maxSizeKb', 321);

    Livewire::test(MediaUploader::class, [
        'for' => $post,
        'collection' => 'images',
        'preset' => 'images',
        'maxSizeKb' => 654,
    ])->assertSet('maxSizeKb', 654);
});

it('preserves explicit type and mime restrictions', function () {
    $post = TestPost::create(['title' => 'Hello']);

    Livewire::test(MediaUploader::class, [
        'for' => $post,
        'collection' => 'images',
        'preset' => 'images',
        'allowedTypes' => ['png'],
        'allowedMimes' => ['image/png'],
    ])
        ->assertSet('allowedTypes', ['png'])
        ->assertSet('allowedMimes', ['image/png']);
});

it('preserves existing media when watermarking fails during replacement', function () {
    $post = TestPost::create(['title' => 'Hello']);
    $original = TemporaryUploadedFile::fake()->image('replace.png', 50, 50);

    Livewire::test(MediaUploader::class, [
        'for' => $post,
        'collection' => 'images',
        'preset' => 'images',
    ])
        ->set('uploads', [$original])
        ->call('uploadFiles');

    $existingMediaId = $post->getFirstMedia('images')->id;
    $replacement = TemporaryUploadedFile::fake()->image('replace.png', 60, 60);

    expect(fn () => Livewire::test(MediaUploader::class, [
        'for' => $post,
        'collection' => 'images',
        'preset' => 'images',
        'watermark' => true,
        'onNameConflict' => 'replace',
    ])
        ->set('uploads', [$replacement])
        ->call('uploadFiles'))
        ->toThrow(RuntimeException::class, 'Configure media-uploader.watermark.path');

    expect($post->fresh()->getMedia('images'))
        ->toHaveCount(1)
        ->and($post->getFirstMedia('images')->id)->toBe($existingMediaId);
});

it('honors the global watermark default and per-uploader override', function () {
    $post = TestPost::create(['title' => 'Hello']);
    config()->set('media-uploader.watermark.enabled', true);

    Livewire::test(MediaUploader::class, [
        'for' => $post,
    ])->assertSet('watermark', true);

    Livewire::test(MediaUploader::class, [
        'for' => $post,
        'watermark' => false,
    ])->assertSet('watermark', false);
});

it('stages uploads from streams without requiring a local source path', function () {
    $remoteFile = new class
    {
        public function readStream()
        {
            $stream = fopen('php://temp', 'w+b');
            fwrite($stream, 'remote-file-contents');
            rewind($stream);

            return $stream;
        }

        public function getRealPath(): string
        {
            throw new RuntimeException('A remote temporary upload has no local path.');
        }
    };

    $preparedPath = (new TestableMediaUploader)->prepareUploadedFileForTest($remoteFile, 'remote.txt');

    expect(pathinfo($preparedPath, PATHINFO_EXTENSION))->toBe('txt')
        ->and(file_get_contents($preparedPath))->toBe('remote-file-contents');

    unlink($preparedPath);
});

it('renames on name conflict by default', function () {
    $post = TestPost::create(['title' => 'Hello']);

    $f1 = TemporaryUploadedFile::fake()->image('dup.jpg', 50, 50)->size(100);
    $f2 = TemporaryUploadedFile::fake()->image('dup.jpg', 50, 50)->size(100);

    // First upload
    Livewire::test(MediaUploader::class, [
        'for' => $post,
        'collection' => 'images',
        'preset' => 'images',
        'onNameConflict' => 'rename',
    ])
        ->set('uploads', [$f1])
        ->call('uploadFiles');

    // Second upload with same name
    Livewire::test(MediaUploader::class, [
        'for' => $post,
        'collection' => 'images',
        'preset' => 'images',
        'onNameConflict' => 'rename',
    ])
        ->set('uploads', [$f2])
        ->call('uploadFiles');

    $files = $post->getMedia('images')->pluck('file_name')->sort()->values()->all();

    expect($files)->toHaveCount(2)
        ->and($files[0])->toBe('dup-(1).jpg')  // Spatie sanitizes spaces → dashes
        ->and($files[1])->toBe('dup.jpg');
});

it('replaces on name conflict when configured', function () {
    $post = TestPost::create(['title' => 'Hello']);

    $f1 = TemporaryUploadedFile::fake()->image('doc.png', 50, 50)->size(50);
    $f2 = TemporaryUploadedFile::fake()->image('doc.png', 50, 50)->size(60);

    Livewire::test(MediaUploader::class, [
        'for' => $post,
        'collection' => 'images',
        'preset' => 'images',
        'onNameConflict' => 'replace',
    ])
        ->set('uploads', [$f1])
        ->call('uploadFiles');

    Livewire::test(MediaUploader::class, [
        'for' => $post,
        'collection' => 'images',
        'preset' => 'images',
        'onNameConflict' => 'replace',
    ])
        ->set('uploads', [$f2])
        ->call('uploadFiles');

    expect($post->getMedia('images'))->toHaveCount(1)
        ->and($post->getFirstMedia('images')->file_name)->toBe('doc.png');
});

it('skips exact duplicates when enabled', function () {
    $post = TestPost::create(['title' => 'Hello']);

    // Use a test-only subclass that forces a constant SHA-256 so both uploads match.
    $t1 = TemporaryUploadedFile::fake()->image('fixed.jpg', 80, 80)->size(150);
    $t2 = TemporaryUploadedFile::fake()->image('fixed.jpg', 80, 80)->size(150);

    Livewire::test(TestableMediaUploader::class, [
        'for' => $post,
        'collection' => 'images',
        'preset' => 'images',
        'skipExactDuplicates' => true,
    ])
        ->set('uploads', [$t1])
        ->call('uploadFiles');

    Livewire::test(TestableMediaUploader::class, [
        'for' => $post,
        'collection' => 'images',
        'preset' => 'images',
        'skipExactDuplicates' => true,
    ])
        ->set('uploads', [$t2])
        ->call('uploadFiles');

    expect($post->getMedia('images'))->toHaveCount(1);
});

it('updates metadata via inline edit', function () {
    $post = TestPost::create(['title' => 'Hello']);
    $file = TemporaryUploadedFile::fake()->image('edit.jpg', 50, 50);

    // Upload one
    Livewire::test(MediaUploader::class, [
        'for' => $post,
        'collection' => 'images',
        'preset' => 'images',
        'showList' => true,
    ])
        ->set('uploads', [$file])
        ->call('uploadFiles');

    $m = $post->getFirstMedia('images');

    // Edit its meta
    Livewire::test(MediaUploader::class, [
        'for' => $post,
        'collection' => 'images',
        'showList' => true,
    ])
        ->call('startEdit', $m->id)
        ->set("editing.{$m->id}.caption", 'New cap')
        ->set("editing.{$m->id}.description", 'New desc')
        ->set("editing.{$m->id}.order", 5)
        ->call('saveEdit', $m->id)
        ->assertDispatched('media-meta-updated', id: $m->id);

    $m->refresh();
    expect($m->getCustomProperty('caption'))->toBe('New cap')
        ->and($m->getCustomProperty('description'))->toBe('New desc')
        ->and($m->order_column)->toBe(5);
});

it('deletes media via confirmation flow', function () {
    $post = TestPost::create(['title' => 'Hello']);
    $file = TemporaryUploadedFile::fake()->image('gone.jpg', 40, 40);

    Livewire::test(MediaUploader::class, [
        'for' => $post,
        'collection' => 'images',
        'preset' => 'images',
        'showList' => true,
    ])
        ->set('uploads', [$file])
        ->call('uploadFiles');

    $m = $post->getFirstMedia('images');

    Livewire::test(MediaUploader::class, [
        'for' => $post,
        'collection' => 'images',
        'showList' => true,
    ])
        ->call('confirmDelete', $m->id)
        ->call('deleteConfirmed')
        ->assertDispatched('media-deleted', id: $m->id);

    $post->refresh();
    expect($post->getMedia('images'))->toHaveCount(0);
});

it('skips on name conflict when configured', function () {
    $post = TestPost::create(['title' => 'Hello']);

    $f1 = TemporaryUploadedFile::fake()->image('doc.png', 50, 50)->size(50);
    $f2 = TemporaryUploadedFile::fake()->image('doc.png', 50, 50)->size(60);

    // Upload first file
    Livewire::test(MediaUploader::class, [
        'for' => $post,
        'onNameConflict' => NameConflictStrategy::SKIP->value,
    ])
        ->set('uploads', [$f1])
        ->call('uploadFiles');

    // Attempt to upload second file with same name
    Livewire::test(MediaUploader::class, [
        'for' => $post,
        'onNameConflict' => NameConflictStrategy::SKIP->value,
    ])
        ->set('uploads', [$f2])
        ->call('uploadFiles');

    // Assert count remains 1 and the existing file is the original one
    expect($post->getMedia('images'))->toHaveCount(1)
        ->and($post->getFirstMedia('images')->file_name)->toBe('doc.png');
});

it('reorders attached media via drag-and-drop', function () {
    $post = TestPost::create(['title' => 'Hello']);

    $aPath = tempnam(sys_get_temp_dir(), 'media-test-');
    $bPath = tempnam(sys_get_temp_dir(), 'media-test-');
    $cPath = tempnam(sys_get_temp_dir(), 'media-test-');

    file_put_contents($aPath, 'a');
    file_put_contents($bPath, 'b');
    file_put_contents($cPath, 'c');

    $a = $post->addMedia($aPath)
        ->usingFileName('a.jpg')
        ->toMediaCollection('images');

    $b = $post->addMedia($bPath)
        ->usingFileName('b.jpg')
        ->toMediaCollection('images');

    $c = $post->addMedia($cPath)
        ->usingFileName('c.jpg')
        ->toMediaCollection('images');

    // Starting order is a(1), b(2), c(3). Drag c before a.
    Livewire::test(MediaUploader::class, [
        'for' => $post,
        'collection' => 'images',
        'showList' => true,
    ])
        ->call('reorderItems', $c->id, $a->id, 'images', 'before')
        ->assertDispatched('media-reordered');

    $order = $post->fresh()
        ->media()
        ->where('collection_name', 'images')
        ->orderBy('order_column')
        ->pluck('file_name')
        ->all();

    expect($order)->toBe([
        'c.jpg',
        'a.jpg',
        'b.jpg',
    ]);
});

it('ignores reorderItems for media outside the resolved collection', function () {
    $post = TestPost::create(['title' => 'Hello']);

    $imagePath = tempnam(sys_get_temp_dir(), 'media-test-');
    $documentPath = tempnam(sys_get_temp_dir(), 'media-test-');

    file_put_contents($imagePath, 'image');
    file_put_contents($documentPath, 'document');

    $a = $post->addMedia($imagePath)
        ->usingFileName('a.jpg')
        ->toMediaCollection('images');

    $doc = $post->addMedia($documentPath)
        ->usingFileName('doc.pdf')
        ->toMediaCollection('documents');

    Livewire::test(MediaUploader::class, [
        'for' => $post,
        'collection' => 'images',
        'showList' => true,
    ])->call(
        'reorderItems',
        $doc->id,
        $a->id,
        'images',
        'before'
    );

    expect($a->fresh()->order_column)->toBe(1)
        ->and($doc->fresh()->collection_name)->toBe('documents');
});

it('reorders the pending upload queue before files are uploaded', function () {
    $post = TestPost::create(['title' => 'Hello']);

    $f1 = TemporaryUploadedFile::fake()->image('one.jpg', 20, 20);
    $f2 = TemporaryUploadedFile::fake()->image('two.jpg', 20, 20);
    $f3 = TemporaryUploadedFile::fake()->image('three.jpg', 20, 20);

    Livewire::test(MediaUploader::class, [
        'for' => $post,
        'collection' => 'images',
        'preset' => 'images',
    ])
        ->set('uploads', [$f1, $f2, $f3])
        ->call('reorderQueue', 2, 0) // drag "three.jpg" (key 2) to the front
        ->assertSet('selected.0.name', 'three.jpg')
        ->assertSet('selected.1.name', 'one.jpg')
        ->assertSet('selected.2.name', 'two.jpg')
        ->assertSet('pendingMeta.0.order', 1)
        ->assertSet('pendingMeta.1.order', 2)
        ->assertSet('pendingMeta.2.order', 3);
});

it('requires the configured channel when attaching deferred uploads', function () {
    $post = TestPost::create(['title' => 'Hello']);
    $file = TemporaryUploadedFile::fake()->image('queued.jpg', 20, 20);
    Gate::define('attach-media', fn (?User $user) => true);

    $component = Livewire::test(MediaUploader::class, [
        'model' => TestPost::class,
        'collection' => 'images',
        'preset' => 'images',
        'channel' => 'post-gallery',
        'authorizeAbility' => 'attach-media',
    ])->set('uploads', [$file]);

    $component->call('attachTo', TestPost::class, $post->id, 'images');

    expect($post->fresh()->getMedia('images'))->toHaveCount(0);

    $component
        ->call('attachTo', TestPost::class, $post->id, 'images', null, 'post-gallery')
        ->assertDispatched('media-attached');

    expect($post->fresh()->getMedia('images'))->toHaveCount(1);
});

it('throws ModelResolutionException if model is not saved', function () {
    $post = new TestPost(['title' => 'Unsaved Post']);

    try {
        Livewire::test(MediaUploader::class, ['for' => $post]);
        $this->fail('ModelResolutionException was not thrown.');
    } catch (Throwable $e) {
        // If it's wrapped in a ViewException, get the inner exception
        $actual = $e instanceof ViewException ? $e->getPrevious() : $e;

        expect($actual)->toBeInstanceOf(ModelResolutionException::class)
            ->and($actual->getMessage())->toBe('Target model must be saved before attaching media.');
    }
});

it('throws ModelResolutionException if model does not implement HasMedia', function () {
    $user = new User;

    try {
        Livewire::test(MediaUploader::class, ['for' => $user]);
        $this->fail('ModelResolutionException was not thrown.');
    } catch (Throwable $e) {
        // If it's wrapped in a ViewException, get the inner exception
        $actual = $e instanceof ViewException ? $e->getPrevious() : $e;

        expect($actual)->toBeInstanceOf(ModelResolutionException::class);
    }
});

it('blocks mutating actions when authorizeAbility fails', function () {
    $post = TestPost::create(['title' => 'Hello']);
    $file = TemporaryUploadedFile::fake()->image('blocked.jpg', 40, 40);

    Gate::define('update', fn ($user, $post) => false);

    Livewire::test(MediaUploader::class, [
        'for' => $post,
        'collection' => 'images',
        'preset' => 'images',
        'authorizeAbility' => 'update',
    ])
        ->set('uploads', [$file])
        ->call('uploadFiles')
        ->assertForbidden();

    expect($post->getMedia('images'))->toHaveCount(0);
});

it('prevents clients from removing the configured authorization ability', function () {
    $post = TestPost::create(['title' => 'Hello']);

    expect(fn () => Livewire::test(MediaUploader::class, [
        'for' => $post,
        'authorizeAbility' => 'update',
    ])->set('authorizeAbility', null))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

it('can move attached media to the end of the collection', function () {
    $post = TestPost::create(['title' => 'Hello']);

    $aPath = tempnam(sys_get_temp_dir(), 'media-test-');
    $bPath = tempnam(sys_get_temp_dir(), 'media-test-');
    $cPath = tempnam(sys_get_temp_dir(), 'media-test-');

    file_put_contents($aPath, 'a');
    file_put_contents($bPath, 'b');
    file_put_contents($cPath, 'c');

    $a = $post->addMedia($aPath)
        ->usingFileName('a.jpg')
        ->toMediaCollection('images');

    $b = $post->addMedia($bPath)
        ->usingFileName('b.jpg')
        ->toMediaCollection('images');

    $c = $post->addMedia($cPath)
        ->usingFileName('c.jpg')
        ->toMediaCollection('images');

    Livewire::test(MediaUploader::class, [
        'for' => $post,
        'collection' => 'images',
        'showList' => true,
    ])
        ->call('reorderItems', $a->id, $c->id, 'images', 'after')
        ->assertDispatched('media-reordered');

    $order = $post->fresh()
        ->media()
        ->where('collection_name', 'images')
        ->orderBy('order_column')
        ->pluck('file_name')
        ->all();

    expect($order)->toBe([
        'b.jpg',
        'c.jpg',
        'a.jpg',
    ]);
});
