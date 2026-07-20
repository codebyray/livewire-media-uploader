<?php

use Codebyray\LivewireMediaUploader\Enums\NameConflictStrategy;
use Codebyray\LivewireMediaUploader\Exceptions\ModelResolutionException;
use Codebyray\LivewireMediaUploader\Livewire\MediaUploader;
use Codebyray\LivewireMediaUploader\Tests\Fixtures\TestableMediaUploader;
use Codebyray\LivewireMediaUploader\Tests\Fixtures\TestPost;
use Illuminate\Foundation\Auth\User;
use Illuminate\View\ViewException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
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
