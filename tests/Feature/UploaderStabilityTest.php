<?php

use Codebyray\LivewireMediaUploader\Livewire\MediaUploader;
use Codebyray\LivewireMediaUploader\Tests\Fixtures\CustomMedia;
use Codebyray\LivewireMediaUploader\Tests\Fixtures\SingleFilePost;
use Codebyray\LivewireMediaUploader\Tests\Fixtures\TestPost;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\Exceptions\DiskCannotBeAccessed;
use Spatie\MediaLibrary\MediaCollections\Exceptions\DiskDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Filesystem;

afterEach(function () {
    Relation::morphMap([], false);
});

it('preserves media display names when staging and renaming uploads', function () {
    $post = TestPost::create(['title' => 'Names']);

    Livewire::test(MediaUploader::class, ['for' => $post])
        ->set('uploads', [
            TemporaryUploadedFile::fake()->image('cover.png', 20, 20),
            TemporaryUploadedFile::fake()->image('cover.png', 30, 30),
        ])
        ->call('uploadFiles');

    $media = $post->fresh()->getMedia('images');
    expect($media->pluck('name')->all())->toBe(['cover', 'cover'])
        ->and($media->pluck('file_name')->all())->toBe(['cover.png', 'cover-(1).png']);
});

it('edits and deletes media owned by a morph-mapped model', function () {
    Relation::morphMap(['post' => TestPost::class], false);
    $post = TestPost::create(['title' => 'Morph']);

    Livewire::test(MediaUploader::class, ['for' => $post])
        ->set('uploads', [TemporaryUploadedFile::fake()->image('cover.png', 20, 20)])
        ->call('uploadFiles');

    $media = $post->getFirstMedia('images');
    expect($media->model_type)->toBe('post');

    $component = Livewire::test(MediaUploader::class, ['for' => $post])
        ->call('startEdit', $media->id)
        ->set("editing.{$media->id}.caption", 'Updated')
        ->call('saveEdit', $media->id)
        ->assertDispatched('media-meta-updated', id: $media->id);

    expect($media->fresh()->getCustomProperty('caption'))->toBe('Updated');

    $component->call('remove', $media->id)->assertDispatched('media-deleted', id: $media->id);
    expect($post->fresh()->getMedia('images'))->toHaveCount(0);
});

it('rejects mutations to another models media', function (string $action) {
    $post = TestPost::create(['title' => 'Bound']);
    $other = TestPost::create(['title' => 'Other']);

    Livewire::test(MediaUploader::class, ['for' => $other])
        ->set('uploads', [TemporaryUploadedFile::fake()->image('cover.png', 20, 20)])
        ->call('uploadFiles');

    $media = $other->getFirstMedia('images');
    Livewire::test(MediaUploader::class, ['for' => $post])->call($action, $media->id)->assertForbidden();
    expect($media->fresh())->not->toBeNull();
})->with(['remove', 'saveEdit']);

it('uses the configured media model for edit reorder and delete', function () {
    Schema::rename('media', 'custom_media');
    config()->set('media-library.media_model', CustomMedia::class);
    $post = TestPost::create(['title' => 'Custom media']);

    Livewire::test(MediaUploader::class, ['for' => $post])
        ->set('uploads', [
            TemporaryUploadedFile::fake()->image('one.png', 20, 20),
            TemporaryUploadedFile::fake()->image('two.png', 30, 30),
        ])
        ->call('uploadFiles');

    [$first, $second] = $post->getMedia('images')->all();
    $component = Livewire::test(MediaUploader::class, ['for' => $post])
        ->call('startEdit', $first->id)
        ->set("editing.{$first->id}.caption", 'Custom caption')
        ->call('saveEdit', $first->id)
        ->call('reorderItems', $second->id, $first->id);

    expect($first->fresh()->getCustomProperty('caption'))->toBe('Custom caption')
        ->and($second->fresh()->order_column)->toBe(1);

    $component->call('remove', $first->id);
    expect($post->fresh()->getMedia('images'))->toHaveCount(1);
});

it('appends reordered uploads after the active collections existing media', function () {
    $post = TestPost::create(['title' => 'Ordering']);
    Livewire::test(MediaUploader::class, ['for' => $post])
        ->set('uploads', [TemporaryUploadedFile::fake()->image('existing.png', 20, 20)])
        ->call('uploadFiles');
    Livewire::test(MediaUploader::class, ['for' => $post, 'collection' => 'other'])
        ->set('uploads', [TemporaryUploadedFile::fake()->image('other.png', 20, 20)])
        ->set('pendingMeta.0.order', 99)
        ->call('uploadFiles');

    Livewire::test(MediaUploader::class, ['for' => $post])
        ->set('uploads', [
            TemporaryUploadedFile::fake()->image('one.png', 20, 20),
            TemporaryUploadedFile::fake()->image('two.png', 30, 30),
        ])
        ->set('pendingMeta.1.caption', 'Keep this caption')
        ->call('reorderQueue', 1, 0)
        ->assertSet('pendingMeta.0.order', 2)
        ->assertSet('pendingMeta.1.order', 3)
        ->assertSet('pendingMeta.0.caption', 'Keep this caption')
        ->call('uploadFiles');

    expect($post->fresh()->getMedia('images')->pluck('file_name')->all())
        ->toBe(['existing.png', 'two.png', 'one.png']);
});

it('starts deferred queue ordering at one after repeated reordering', function () {
    Livewire::test(MediaUploader::class, ['model' => TestPost::class])
        ->set('uploads', [
            TemporaryUploadedFile::fake()->image('one.png', 20, 20),
            TemporaryUploadedFile::fake()->image('two.png', 30, 30),
        ])
        ->call('reorderQueue', 1, 0)
        ->call('reorderQueue', 1, 0)
        ->assertSet('pendingMeta.0.order', 1)
        ->assertSet('pendingMeta.1.order', 2);
});

it('does not allow an already bound uploader to switch targets', function () {
    $post = TestPost::create(['title' => 'Bound']);
    $other = TestPost::create(['title' => 'Other']);
    Livewire::test(MediaUploader::class, ['for' => $post])
        ->call('attachTo', TestPost::class, $other->id)
        ->assertForbidden();
});

it('requires authorization before resolving a deferred target', function () {
    $post = TestPost::create(['title' => 'Deferred']);
    Livewire::test(MediaUploader::class, ['model' => TestPost::class])
        ->set('uploads', [TemporaryUploadedFile::fake()->image('cover.png', 20, 20)])
        ->call('attachTo', TestPost::class, $post->id)
        ->assertForbidden();

    expect($post->fresh()->getMedia('images'))->toHaveCount(0);
});

it('checks the selected records policy before binding deferred media', function () {
    $allowed = TestPost::create(['title' => 'Allowed']);
    $denied = TestPost::create(['title' => 'Denied']);
    Gate::define('manage-media', fn (?User $user, TestPost $target) => $target->is($allowed));

    $component = Livewire::test(MediaUploader::class, [
        'model' => TestPost::class,
        'authorizeAbility' => 'manage-media',
    ])->set('uploads', [TemporaryUploadedFile::fake()->image('cover.png', 20, 20)]);

    $component->call('attachTo', TestPost::class, $denied->id)->assertForbidden();
    expect($denied->fresh()->getMedia('images'))->toHaveCount(0);

    Livewire::test(MediaUploader::class, [
        'model' => TestPost::class,
        'authorizeAbility' => 'manage-media',
    ])
        ->set('uploads', [TemporaryUploadedFile::fake()->image('cover.png', 20, 20)])
        ->call('attachTo', TestPost::class, $allowed->id)
        ->assertDispatched('media-attached');
    expect($allowed->fresh()->getMedia('images'))->toHaveCount(1);
});

it('locks the expected deferred model class against client updates', function () {
    expect(fn () => Livewire::test(MediaUploader::class, ['model' => TestPost::class])
        ->set('pendingModelClass', SingleFilePost::class))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

it('rejects an attach request for a different model class', function () {
    $other = SingleFilePost::create(['title' => 'Different class']);
    Gate::define('manage-media', fn (?User $user) => true);

    Livewire::test(MediaUploader::class, [
        'model' => TestPost::class,
        'authorizeAbility' => 'manage-media',
    ])->call('attachTo', SingleFilePost::class, $other->id)->assertForbidden();
});

it('ignores attachment broadcasts for a different collection', function () {
    $post = TestPost::create(['title' => 'Collection']);
    Gate::define('manage-media', fn (?User $user) => true);

    Livewire::test(MediaUploader::class, [
        'model' => TestPost::class,
        'collection' => 'images',
        'authorizeAbility' => 'manage-media',
    ])
        ->set('uploads', [TemporaryUploadedFile::fake()->image('cover.png', 20, 20)])
        ->call('attachTo', TestPost::class, $post->id, 'other')
        ->assertSet('resolvedModelId', null)
        ->assertSet('collection', 'images')
        ->assertNotDispatched('media-attached');

    expect($post->fresh()->getMedia('*'))->toHaveCount(0);
});

it('rejects attach arguments that change the configured disk', function () {
    $post = TestPost::create(['title' => 'Disk']);
    Gate::define('manage-media', fn (?User $user) => true);

    Livewire::test(MediaUploader::class, [
        'model' => TestPost::class,
        'disk' => 'public',
        'authorizeAbility' => 'manage-media',
    ])
        ->set('uploads', [TemporaryUploadedFile::fake()->image('cover.png', 20, 20)])
        ->call('attachTo', TestPost::class, $post->id, 'images', 'tmp-for-tests')
        ->assertForbidden();

    expect($post->fresh()->getMedia('images'))->toHaveCount(0);
});

it('supports matching disk and morph alias arguments for authorized deferred uploads', function () {
    Relation::morphMap(['post' => TestPost::class], false);
    $post = TestPost::create(['title' => 'Matching arguments']);
    Gate::define('manage-media', fn (?User $user) => true);

    Livewire::test(MediaUploader::class, [
        'model' => 'post',
        'disk' => 'public',
        'authorizeAbility' => 'manage-media',
    ])
        ->set('uploads', [TemporaryUploadedFile::fake()->image('cover.png', 20, 20)])
        ->call('attachTo', 'post', $post->id, 'images', 'public')
        ->assertDispatched('media-attached');

    expect($post->fresh()->getFirstMedia('images')->disk)->toBe('public');
});

it('keeps a deferred uploader bound to its first authorized target', function () {
    $post = TestPost::create(['title' => 'First target']);
    $other = TestPost::create(['title' => 'Another authorized target']);
    Gate::define('manage-media', fn (?User $user) => true);

    $component = Livewire::test(MediaUploader::class, [
        'model' => TestPost::class,
        'authorizeAbility' => 'manage-media',
    ])
        ->set('uploads', [TemporaryUploadedFile::fake()->image('cover.png', 20, 20)])
        ->call('attachTo', TestPost::class, $post->id)
        ->call('attachTo', TestPost::class, $post->id)
        ->assertSet('resolvedModelId', (string) $post->id);

    expect($post->fresh()->getMedia('images'))->toHaveCount(1);

    $component->call('attachTo', TestPost::class, $other->id)->assertForbidden();
    expect($other->fresh()->getMedia('images'))->toHaveCount(0);
});

it('enforces authorization on existing media mutations', function (string $action) {
    $post = TestPost::create(['title' => 'Authorization']);
    Livewire::test(MediaUploader::class, ['for' => $post])
        ->set('uploads', [
            TemporaryUploadedFile::fake()->image('one.png', 20, 20),
            TemporaryUploadedFile::fake()->image('two.png', 30, 30),
        ])
        ->call('uploadFiles');
    [$first, $second] = $post->getMedia('images')->all();
    Gate::define('manage-media', fn (?User $user) => false);

    $component = Livewire::test(MediaUploader::class, [
        'for' => $post,
        'authorizeAbility' => 'manage-media',
    ]);
    $arguments = $action === 'reorderItems' ? [$second->id, $first->id] : [$first->id];
    $component->call($action, ...$arguments)->assertForbidden();

    expect($post->fresh()->getMedia('images'))->toHaveCount(2)
        ->and($first->fresh()->getCustomProperty('caption'))->toBeNull()
        ->and($second->fresh()->order_column)->toBe(2);
})->with(['remove', 'saveEdit', 'reorderItems']);

it('renders the maintenance flows in both supplied themes', function (string $theme) {
    $post = TestPost::create(['title' => 'Themes']);
    $component = Livewire::test(MediaUploader::class, ['for' => $post, 'theme' => $theme])
        ->assertSee('Manage gallery')
        ->set('uploads', [TemporaryUploadedFile::fake()->image('cover.png', 20, 20)])
        ->call('uploadFiles')
        ->assertSee('cover.png');
    $media = $post->fresh()->getFirstMedia('images');

    $component
        ->call('startEdit', $media->id)
        ->set("editing.{$media->id}.caption", 'Theme caption')
        ->call('saveEdit', $media->id)
        ->assertSee('Theme caption')
        ->call('confirmDelete', $media->id)
        ->call('deleteConfirmed')
        ->assertDispatched('media-deleted', id: $media->id);

    expect($post->fresh()->getMedia('images'))->toHaveCount(0);
})->with(['tailwind', 'bootstrap']);

it('preserves the original media when the replacement disk is unconfigured', function () {
    $post = TestPost::create(['title' => 'Replacement']);
    Livewire::test(MediaUploader::class, ['for' => $post])
        ->set('uploads', [TemporaryUploadedFile::fake()->image('cover.png', 20, 20)])
        ->call('uploadFiles');
    $original = $post->getFirstMedia('images');
    $originalHash = hash_file('sha256', $original->getPath());

    expect(fn () => Livewire::test(MediaUploader::class, [
        'for' => $post,
        'onNameConflict' => 'replace',
        'disk' => 'unconfigured-disk',
    ])
        ->set('uploads', [TemporaryUploadedFile::fake()->image('cover.png', 30, 30)])
        ->call('uploadFiles'))
        ->toThrow(DiskDoesNotExist::class);

    expect($original->fresh())->not->toBeNull()
        ->and(hash_file('sha256', $original->getPath()))->toBe($originalHash)
        ->and($post->fresh()->getMedia('images'))->toHaveCount(1);
});

it('preserves original media when the replacement cannot be written', function (string $modelClass) {
    $post = $modelClass::create(['title' => 'Write failure']);
    Livewire::test(MediaUploader::class, ['for' => $post])
        ->set('uploads', [TemporaryUploadedFile::fake()->image('cover.png', 20, 20)])
        ->call('uploadFiles');
    $original = $post->getFirstMedia('images');
    $originalHash = hash_file('sha256', $original->getPath());

    $filesystem = Mockery::mock(Filesystem::class)->makePartial();
    $filesystem->shouldReceive('add')->once()->andReturn(false);
    app()->instance(Filesystem::class, $filesystem);

    expect(fn () => Livewire::test(MediaUploader::class, [
        'for' => $post,
        'onNameConflict' => 'replace',
    ])
        ->set('uploads', [TemporaryUploadedFile::fake()->image('cover.png', 30, 30)])
        ->call('uploadFiles'))
        ->toThrow(DiskCannotBeAccessed::class);

    expect($original->fresh())->not->toBeNull()
        ->and(hash_file('sha256', $original->getPath()))->toBe($originalHash)
        ->and($post->fresh()->getMedia('images'))->toHaveCount(1);
})->with(['gallery' => [TestPost::class], 'single file' => [SingleFilePost::class]]);

it('stores replacement metadata before removing the original media', function (string $modelClass) {
    $post = $modelClass::create(['title' => 'Successful replacement']);
    Livewire::test(MediaUploader::class, ['for' => $post])
        ->set('uploads', [TemporaryUploadedFile::fake()->image('cover.png', 20, 20)])
        ->call('uploadFiles');
    $original = $post->getFirstMedia('images');
    $originalPath = $original->getPath();

    Livewire::test(MediaUploader::class, ['for' => $post, 'onNameConflict' => 'replace'])
        ->set('uploads', [TemporaryUploadedFile::fake()->image('cover.png', 30, 30)])
        ->set('pendingMeta.0.caption', 'Replacement caption')
        ->set('pendingMeta.0.order', 5)
        ->call('uploadFiles');

    $replacement = $post->fresh()->getFirstMedia('images');
    expect($post->fresh()->getMedia('images'))->toHaveCount(1)
        ->and($replacement->id)->not->toBe($original->id)
        ->and($replacement->name)->toBe('cover')
        ->and($replacement->getCustomProperty('caption'))->toBe('Replacement caption')
        ->and($replacement->order_column)->toBe(5)
        ->and(is_file($replacement->getPath()))->toBeTrue()
        ->and(is_file($originalPath))->toBeFalse();
})->with(['gallery' => [TestPost::class], 'single file' => [SingleFilePost::class]]);
