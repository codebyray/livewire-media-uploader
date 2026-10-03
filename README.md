<img width="2655" height="1419" alt="media_uploader_screenshot" src="https://github.com/user-attachments/assets/0852d2c2-762a-440c-ad61-8c0b6939f930" />

# Livewire Media Uploader

[![tests](https://github.com/codebyray/livewire-media-uploader/actions/workflows/tests.yml/badge.svg)](https://github.com/codebyray/livewire-media-uploader/actions/workflows/tests.yml)

Livewire Media Uploader is a reusable Livewire v3/v4 component that integrates seamlessly with Spatie Laravel Media Library. It ships a clean Tailwind Blade view by default (fully publishable), Bootstrap theme as an option, Alpine overlays for previews/confirmations, drag-and-drop uploads, per-file metadata (caption/description/order), configurable presets, image watermarking, name-conflict strategies, and optional SHA-256 duplicate detection. Drop it in, point it at a model, and you’re shipping in minutes.

Upgrading from `v0.7.x`? Read [Upgrading to v0.8.0](#upgrading-to-v080) before deploying, especially if you use deferred uploads on create forms.

---

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Upgrading to v0.8.0](#upgrading-to-v080)
- [Publishing Assets](#publishing-assets)
- [Theme System](#theme-system-tailwind--bootstrap--custom)
    - [Dark Mode - Tailwind](#dark-mode-tailwind-theme)
    - [Custom Theme](#custom-themes)
- [Quick Start](#quick-start)
- [Usage Examples](#usage-examples)
    - [Create flow (deferred uploads)](#create-flow-deferred-uploads)
- [Configuration](#configuration)
- [Watermarking](#watermarking)
- [Props](#props)
- [Events](#events)
- [Authorization](#authorization)
- [Model Setup (Spatie Media Library)](#model-setup-spatie-media-library)
- [Overlays & UX Notes](#overlays--ux-notes)
- [Troubleshooting](#troubleshooting)
- [Roadmap](#roadmap)
- [License](#license)

---

## Features

- ✅ Livewire v3/v4 component with themeable Blade UI
    - Tailwind (default)
    - Bootstrap (optional)
    - Fully publishable and overridable
- ✅ Spatie Media Library integration (attach, list, edit meta, delete)
- ✅ **Publishable view** for per-project customization
- ✅ Drag & drop uploads + progress bar
- ✅ Inline edit of **caption / description / order**
- ✅ Drag-to-reorder attached media (`order_column`)
- ✅ Name-conflict strategies: **rename | replace | skip | allow**
- ✅ Optional **exact duplicate** detection via SHA-256
- ✅ Optional server-side **image watermarking** with configurable placement, size, padding, and opacity
- ✅ Collection → preset mapping (auto `accept` attribute)
- ✅ Image preview **overlay** + delete confirmation **modal**
- ✅ **Authorization hook** (`authorizeAbility`) — required for deferred attachment, optional for a saved target fixed at mount; delegates to your app's Gate/Policy
- ✅ Works with:
    - Saved model instance (`:for="$model"`)
    - String model + id (`model="user" :id="1"`)
    - FQCN, morph map alias, or dotted paths with custom namespaces
    - Local alias map

---

## Requirements

- PHP **8.2+** (Laravel 13 requires **8.3+**)
- Laravel **^12.0 | ^13.0**
- Livewire **^3.8.3 | ^4.3.4**
- spatie/laravel-medialibrary **^11.0**
- spatie/image **^3.3.2**
- TailwindCSS (optional but recommended for the default view)
- Alpine.js (used by overlays/progress; see [Overlays & UX Notes](#overlays--ux-notes))
- CSS depending on theme:
    - Tailwind theme → TailwindCSS (recommended)
    - Bootstrap theme → Bootstrap CSS (no Bootstrap JS required; Alpine drives modals)

> **Note on Laravel 10/11:** Earlier releases of this package listed Laravel 10 and 11 as supported. Both are now past their security-support window (Laravel 10 is EOL; Laravel 11 security support ended March 2026), and current releases of `laravel/framework` in those lines carry known, unpatched advisories — meaning a fresh `composer install` targeting either will be blocked by Composer's own audit for most consumers. Support for both has been dropped as of `v0.5.0`. If you're still running Laravel 10/11, pin this package to `v0.4.x`, but prioritize upgrading Laravel first — that's the more urgent fix.
>
> Every supported PHP/Laravel/Livewire combination is verified on pull requests and pushes to the configured release branches via [GitHub Actions](https://github.com/codebyray/livewire-media-uploader/actions/workflows/tests.yml).

---

## Installation

```bash
composer require codebyray/livewire-media-uploader
```

Auto-discovery will register the service provider. If you disable discovery, add:

```php
// config/app.php
'providers' => [
    // ...
    Codebyray\LivewireMediaUploader\MediaUploaderServiceProvider::class,
],
```

The component is registered under **both** aliases:

- `<livewire:media-uploader ... />`
- `<livewire:media.media-uploader ... />`

---

## Upgrading to v0.8.0

`v0.8.0` fixes attachment security, replacement failures, morph-map ownership checks, queue ordering, and uploaded media names. The dependency requirements are unchanged from `v0.7.0`; the attachment rules below are intentional behavior changes.

Update your Composer constraint when the release is available:

```bash
composer require codebyray/livewire-media-uploader:^0.8.0 --with-all-dependencies
```

### Deferred uploads require a policy

If an uploader uses `model` without an `id`, add `authorizeAbility` and ensure its Gate/Policy permits the current user to manage media on the newly saved record:

```blade
<livewire:media-uploader
    model="post"
    collection="images"
    disk="public"
    authorizeAbility="update"
    channel="post-images"
/>
```

For posts owned by a user, an example `App\Policies\PostPolicy` is:

```php
namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function update(User $user, Post $post): bool
    {
        return (string) $post->user_id === (string) $user->getKey();
    }
}
```

Use your application's actual ownership or permission rules. Register the policy if your app does not discover it automatically. Without an ability, or if its policy denies access, deferred attachment returns `403`. A create-page authorization check alone does not authorize the record ID supplied to an attach event.

After saving the post, dispatch the matching event:

```php
$this->dispatch(
    'media:attach',
    model: 'post',
    id: $post->id,
    collection: 'images',
    channel: 'post-images',
);
```

### Configure attachment settings on the uploader

Move any `collection` or `disk` overrides from attach events to the uploader's props. These event arguments can only confirm the configured settings: a different collection is ignored, and a different disk returns `403`. Omit `disk` from the event to use the uploader's configured/default disk.

The initial attachment must use the configured deferred model class, and an already-bound uploader cannot switch records. Use distinct channels for multiple uploader instances. To edit another record, mount a new uploader for that record.

Uploaders already bound to a saved record through `:for` or `model` plus `id` do not require a new prop, provided your application already authorizes access to that fixed record. The optional authorization hook still enforces a policy on their mutations.

No package database migration or new config key is required. Existing media names are not rewritten; preserving the original display name applies to new uploads. See the [changelog](CHANGELOG.md) for the complete release notes.

---

## Publishing Assets

### Config:
```bash
php artisan vendor:publish --tag=media-uploader-config
```

### Views:
```bash
php artisan vendor:publish --tag=media-uploader-views
```

After publishing, customize the Blade at:
```html
resources/views/vendor/media-uploader/themes/tailwind/media-uploader.blade.php
resources/views/vendor/media-uploader/themes/bootstrap/media-uploader.blade.php
```
## Theme System (Tailwind + Bootstrap + custom)
Select the theme in config/media-uploader.php:
```php
// config/media-uploader.php
return [
    'theme'  => 'tailwind', // 'tailwind' (default) or 'bootstrap'
    'themes' => [
        'tailwind'  => 'media-uploader::themes.tailwind.media-uploader',
        'bootstrap' => 'media-uploader::themes.bootstrap.media-uploader',
    ],
    // ...
];
```
### Dark mode (Tailwind theme)
This package’s Tailwind theme is dark-ready. Add this tiny snippet in your main layout `<head>` to apply the user’s saved choice / system default:

```html
<script>
    (() => {
        const t = localStorage.theme ?? 'system';
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const dark = t === 'dark' || (t === 'system' && prefersDark);
        if (dark) document.documentElement.classList.add('dark');
    })();
</script>
```
### Custom themes
- Copy an existing theme directory (e.g. themes/tailwind) to themes/custom and edit the Blade.
- Register it in the map and select it:
    ```php
    'theme'  => 'custom',
    'themes' => [
        'tailwind'  => 'media-uploader::themes.tailwind.media-uploader',
        'bootstrap' => 'media-uploader::themes.bootstrap.media-uploader',
        'custom'    => 'media-uploader::themes.custom.media-uploader',
    ],
    ```
  > Note: The component’s Livewire + Alpine behavior is identical across themes. Only classes/markup differ. If you use the Bootstrap theme, make sure your layout includes Bootstrap CSS.
## Environment variables (optional)
You can override preset limits and accepted types/mimes via .env. These map directly to config/media-uploader.php:

```dotenv
# Livewire Media Uploader (optional)

# Images
MEDIA_TYPES_IMAGES=jpg,jpeg,png,webp,avif,gif
MEDIA_MIMES_IMAGES=image/jpeg,image/png,image/webp,image/avif,image/gif
MEDIA_MAXKB_IMAGES=10240

# Documents
MEDIA_TYPES_DOCS=pdf,doc,docx,xls,xlsx,ppt,pptx,txt
MEDIA_MIMES_DOCS=application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,text/plain
MEDIA_MAXKB_DOCS=20480

# Videos
MEDIA_TYPES_VIDEOS=mp4,mov,webm
MEDIA_MIMES_VIDEOS=video/mp4,video/quicktime,video/webm
MEDIA_MAXKB_VIDEOS=102400

# Fallback preset
MEDIA_TYPES_DEFAULT=jpg,jpeg,png,webp,avif,gif,pdf,doc,docx,xls,xlsx,ppt,pptx,txt
MEDIA_MIMES_DEFAULT=image/jpeg,image/png,image/webp,image/avif,image/gif,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,text/plain
MEDIA_MAXKB_DEFAULT=10240

# Image watermarking
MEDIA_UPLOADER_WATERMARK_ENABLED=false
MEDIA_UPLOADER_WATERMARK_PATH=/absolute/path/to/watermark.png
```

### Notes
- Values are comma-separated; spaces are OK (the package trims them).
- After changing .env, run:
    ```bash
    php artisan config:clear
    ```
- The `````<input accept="…">````` attribute is auto-filled from the active preset when accept_from_config is true (default). You can still override it per-component with the accept prop.
- If uploads fail due to size, make sure your PHP/Server limits also allow it (e.g. upload_max_filesize, post_max_size).

---

## Quick Start

1) Ensure your target Eloquent model implements `Spatie\MediaLibrary\HasMedia` and is **saved**.

   #### Model Setup (Spatie Media Library)

   Your model must implement `HasMedia` and be **saved** before attaching media.

    ```php
    use Spatie\Image\Enums\Fit;
    use Spatie\MediaLibrary\HasMedia;
    use Spatie\MediaLibrary\InteractsWithMedia;
    use Spatie\MediaLibrary\MediaCollections\Models\Media;
    
    class Post extends Model implements HasMedia
    {
        use InteractsWithMedia;
    
        public function registerMediaCollections(): void
        {
            // Multi-file collection.
            $this->addMediaCollection('photos')
                ->useDisk('public')
                ->withResponsiveImages();
    
            // Single-file collection. Each new upload replaces the existing file.
            $this->addMediaCollection('avatars')
                ->singleFile();
        }
    
        public function registerMediaConversions(?Media $media = null): void
        {
            $this->addMediaConversion('thumb')
                ->fit(Fit::Contain, 256, 256)
                ->performOnCollections('photos', 'avatars')
                ->nonQueued();
        }
    }
    ```
   > **Multiple uploads and `singleFile()`**
    >
    > The uploader's `multiple` prop controls whether the file input allows selecting multiple files. It does **not** override Spatie Media Library's collection configuration.
    >
    > If a collection is configured with `->singleFile()`, Spatie will remove the existing media item each time another file is added. Selecting multiple files can therefore process every selected file while leaving only the last file in the collection.
    >
    > For galleries and other multi-file collections, do not use `->singleFile()`:
    >
    > ```php
    > $this->addMediaCollection('photos');
    > ```
    >
    > Use `->singleFile()` only when the collection should contain one item, such as an avatar or logo:
    >
    > ```php
    > $this->addMediaCollection('avatar')
    >     ->singleFile();
    > ```
2) Include Livewire & Alpine (usually in your app layout):

    ```html
    @livewireStyles
    <style>[x-cloak]{ display:none !important; }</style>
    @livewireScripts
    ```

3) Drop the component into your Blade:

    ```html
    <livewire:media-uploader :for="$user" collection="avatars" preset="images" />
    ```

---

## Usage Examples

1) Pass a saved model instance
    ```html
    <livewire:media-uploader :for="$user" collection="avatars" preset="images" />
    ```

2) Short string model + id
    ```html
    <livewire:media-uploader model="user" :id="$user->id" collection="images" preset="images" />
    ```

3) Morph map alias**
    ```html
    <livewire:media-uploader model="users" :id="$user->id" collection="profile" preset="images" />
    ```

4) FQCN
    ```html
    <livewire:media-uploader model="\App\Models\User" :id="$user->id" collection="documents" />
    ```

5) Dotted path + custom namespaces
    ```html
    <livewire:media-uploader
        model="crm.contact"
        :id="$contactId"
        :namespaces="['App\\Domain\\Crm\\Models', 'App\\Models']"
        collection="images"
        preset="images"
    />
    ```

6) Local aliases (per-instance)
    ```html
    <livewire:media-uploader
        model="profile"
        :id="$user->id"
        :aliases="['profile' => \App\Models\User::class]"
        collection="gallery"
    />
    ```

7) Single-file mode + hide list
    ```html
    <livewire:media-uploader
        :for="$user"
        collection="avatar"
        :multiple="false"
        :showList="false"
        preset="images"
    />
    ```

8) Name conflict strategies
    ```html
    <livewire:media-uploader :for="$user" collection="files" onNameConflict="rename" />
    <livewire:media-uploader :for="$user" collection="files" onNameConflict="replace" />
    <livewire:media-uploader :for="$user" collection="files" onNameConflict="skip" />
    <livewire:media-uploader :for="$user" collection="files" onNameConflict="allow" />
    ```

9) Duplicate detection by SHA-256
    ```html
    <livewire:media-uploader :for="$user" collection="images" preset="images" :skipExactDuplicates="true" />
    ```

10) Restrict types/mimes/max size manually
    ```html
    <livewire:media-uploader
        :for="$user"
        collection="documents"
        :accept="'.pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document'"
        :allowedTypes="['pdf','doc','docx']"
        :allowedMimes="['application/pdf','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document']"
        :maxSizeKb="5120"
    />
    ```

11) Watermark image uploads

    ```html
    <livewire:media-uploader
        :for="$post"
        collection="images"
        preset="images"
        :watermark="true"
    />
    ```

    See [Watermarking](#watermarking) for setup, every available option, and processing behavior.

### Create flow (deferred uploads)

You can let users pick files **before** the model exists, and attach them **after** save.

**Blade (create page)**
```html
<!-- Note: pass model class/alias without id -->
<livewire:media-uploader
    model="post"
    collection="images"
    preset="images"
    :multiple="true"
    :showList="true"
    authorizeAbility="update"
    channel="post-images"
/>
```

Deferred attachment requires `authorizeAbility`. Define a Gate or Policy that permits the current user to update the newly saved record (for example, only its owner). A create-page authorization check alone does not authorize the record ID supplied to an attach event.

#### Livewire component (simplified)
```php
use App\Models\Post;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class PostCreate extends Component
{
    public string $title = '';
    public string $body  = '';
    public ?int $pendingPostId = null;

    protected function rules(): array
    {
        return ['title' => 'required|string|max:255', 'body' => 'required|string'];
    }

    public function save(): void
    {
        $post = Post::create([
            'user_id' => Auth::id(),
            'title'   => $this->title,
            'body'    => $this->body,
        ]);

        // Let uploaders attach everything queued for this collection
        $this->pendingPostId = $post->id;

        // Fire once per collection rendered on the page
        $this->dispatch('media:attach', model: 'post', id: $post->id, collection: 'images', channel: 'post-images');
    }

    #[On('media-attached')]
    public function afterMediaAttached(string $model, string|int $id): void
    {
        if ($this->pendingPostId && (int)$id === (int)$this->pendingPostId) {
            $this->pendingPostId = null;
            $this->redirectRoute('posts.show', ['post' => $id], navigate: true);
        }
    }

    public function render() { return view('livewire.posts.post-create'); }
}
```

#### How it works
- On create screens, the component accepts model="post" without an id.
- Files and per-file metadata are queued locally.
- The component's `authorizeAbility` is checked against the saved record before the uploader binds to it. The model class must match the one configured at mount.
- A supplied collection must match the uploader's configured collection. A supplied disk must match its explicit `disk` prop; omit the event's disk argument to use the uploader's configured/default disk. Attach events cannot override either setting.
- Once bound, an uploader cannot switch to another record. Give each deferred uploader a distinct channel when several uploaders share a page.
- After you persist the model, dispatch:
    ```php
        $this->dispatch('media:attach', model: 'post', id: $post->id, collection: 'images', channel: 'post-images');
    ```
- The uploader resolves the saved target, attaches any queued files, and emits media-attached.
---

## Configuration

Publish the package configuration before customizing global behavior:

```bash
php artisan vendor:publish --tag=media-uploader-config
```

The published file is `config/media-uploader.php`. Laravel automatically merges the package defaults when the file has not been published.

### Configuration reference

| Key | Default | Purpose |
|---|---|---|
| `model_namespaces` | `['App\\Models']` | Namespaces searched when resolving short or dotted model names. They are checked in array order. |
| `theme` | `tailwind` | Default theme key. It must exist in the `themes` map. Set it with `MEDIA_UPLOADER_THEME` or override it using the component's `theme` prop. |
| `themes` | Tailwind and Bootstrap views | Maps theme keys to fully qualified Blade view names. Add published custom themes here. |
| `accept_from_config` | `true` | Builds the file input's `accept` attribute from the active preset's MIME types and extensions. This is a browser hint; server validation still runs independently. |
| `watermark` | Disabled | Controls image watermark processing. See [Watermarking](#watermarking). |
| `collections` | Common collection mappings | Maps a Media Library collection name to a validation preset. |
| `presets` | `images`, `docs`, `videos`, `default` | Defines reusable upload validation and file-picker rules. |

### Collection and preset resolution

Collections and presets are separate concepts. `collection="avatars"` selects the Spatie Media Library collection, while the collection map selects which validation preset applies:

```php
'collections' => [
    'avatars' => 'images',
    'images' => 'images',
    'attachments' => 'docs',
],
```

For `collection="avatars"`, the component uses the `images` preset unless the component receives an explicit `preset` prop.

The active preset is resolved in this order:

1. The component's explicit `preset` prop.
2. The preset mapped from the active collection in `collections`.
3. The `default` preset.

Each preset supports:

| Key | Meaning |
|---|---|
| `types` | Comma-separated filename extensions. These are used for validation and, when enabled, the input's `accept` attribute. |
| `mimes` | Comma-separated MIME types used for server-side validation and the generated `accept` attribute. |
| `max_kb` | Maximum size of each uploaded file in kilobytes. PHP, the web server, and Livewire upload limits must also allow this size. |

You can add your own preset and map any collection to it:

```php
'collections' => [
    'press-kits' => 'archives',
],

'presets' => [
    'archives' => [
        'types' => 'zip',
        'mimes' => 'application/zip',
        'max_kb' => 51200,
    ],
],
```

The built-in preset values can be overridden through the environment variables listed in [Environment variables](#environment-variables-optional). After changing configuration in a cached production application, run `php artisan config:clear` or rebuild the configuration cache.

### Showing every collection

Set `:list-all="true"` to render a grouped list of every collection on the target model. Items remain editable, and ordering stays scoped to each collection.

```html
<livewire:media-uploader
    :for="$post"
    :list-all="true"
    :showList="true"
/>
```

## Watermarking

Watermarking is disabled by default. When enabled, the uploader composites a server-side image over each uploaded image before passing the file to Spatie Media Library. The stored original and any Media Library conversions generated from it therefore contain the watermark. Documents, videos, and other non-image uploads are not changed.

### Configure the watermark

Publish `config/media-uploader.php`, place a watermark image somewhere readable by PHP, and configure the complete `watermark` section:

```php
'watermark' => [
    'enabled' => (bool) env('MEDIA_UPLOADER_WATERMARK_ENABLED', false),
    'path' => env(
        'MEDIA_UPLOADER_WATERMARK_PATH',
        public_path('images/watermark.png'),
    ),
    'position' => 'bottom-right',
    'padding_x' => 24,
    'padding_y' => 24,
    'padding_unit' => 'pixel',
    'width' => 20,
    'width_unit' => 'percent',
    'height' => 0,
    'height_unit' => 'pixel',
    'fit' => 'contain',
    'opacity' => 70,
],
```

A transparent PNG is usually the best watermark source. `path` must resolve to a readable local file; it is not a URL or a filesystem-disk key.

### Watermark options

| Key | Accepted values | Description |
|---|---|---|
| `enabled` | `true` or `false` | Global default. An uploader's `watermark` prop can override it. |
| `path` | Readable local path | Source watermark image. An enabled image upload fails with a clear exception when this is missing or unreadable. |
| `position` | `top-left`, `top`, `top-right`, `left`, `center`, `right`, `bottom-left`, `bottom`, `bottom-right` | Alignment of the watermark within the uploaded image. |
| `padding_x` | Integer | Horizontal distance from the aligned edge. |
| `padding_y` | Integer | Vertical distance from the aligned edge. |
| `padding_unit` | `pixel` or `percent` | Unit shared by `padding_x` and `padding_y`. Percentage padding is relative to the uploaded image dimensions. |
| `width` | Integer | Desired watermark width. Set to `0` to derive it from `height`. |
| `width_unit` | `pixel` or `percent` | Unit for `width`. The default `20` percent makes the watermark responsive to source-image width. |
| `height` | Integer | Desired watermark height. Set to `0` to preserve the watermark's aspect ratio from its configured width. |
| `height_unit` | `pixel` or `percent` | Unit for `height`. |
| `fit` | `contain`, `max`, `fill`, `fill-max`, `stretch`, or `crop` | Resize behavior when both width and height are set. With the default `height` of `0`, the original aspect ratio is preserved. |
| `opacity` | Integer from `0` to `100` | Watermark opacity, where `0` is invisible and `100` is fully opaque. |

### Enable it globally or per uploader

To watermark every image handled by the package, set the global configuration:

```dotenv
MEDIA_UPLOADER_WATERMARK_ENABLED=true
MEDIA_UPLOADER_WATERMARK_PATH=/var/www/example.com/public/images/watermark.png
```

To enable it only for selected uploaders, leave the global setting disabled and pass `:watermark="true"`:

```html
<livewire:media-uploader
    :for="$post"
    collection="images"
    preset="images"
    :watermark="true"
/>
```

To disable it for one uploader when the global setting is enabled, pass `:watermark="false"`:

```html
<livewire:media-uploader
    :for="$post"
    collection="private-images"
    preset="images"
    :watermark="false"
/>
```

The prop is optional. When omitted, the uploader uses `watermark.enabled` from configuration. The prop only controls whether processing runs; the path and rendering options remain server-controlled in the configuration file.

### Processing behavior and requirements

- Validation and exact-duplicate detection run before watermark processing.
- Name-conflict handling and Media Library storage run after the watermark is successfully applied. During the `replace` strategy, existing media is deleted only after the replacement and its metadata have been stored successfully, so watermark and storage failures preserve the original.
- Multiple image uploads are processed individually.
- Deferred create-form uploads are watermarked when the queued files are attached to the saved model.
- Processing happens on Livewire's local temporary upload before the selected Media Library disk receives the file, including when the destination disk is remote.
- The package uses the image driver configured by Spatie Media Library in `media-library.image_driver`. That driver must support both the uploaded format and the watermark format.
- The uploader never modifies the file on the user's computer; only the server-side temporary upload and stored copy are changed.

---

## Props

| Prop | Type | Default | Description |
|---|---|---|---|
| `for` | `Model` | — | Saved Eloquent model instance implementing `HasMedia`. |
| `model` | `string` | — | Model resolver: alias, FQCN, morph alias, or dotted path. |
| `id` | `int|string` | — | Target model id (used with `model`). |
| `collection` | `string` | `images` | Media collection name. |
| `disk` | `?string` | `null` | Storage disk (e.g. `s3`). |
| `multiple` | `bool` | `true` | Allow selecting multiple files. The target Spatie collection must not use `singleFile()` if multiple files should be retained. |
| `accept` | `?string` | `null` | `<input accept>` override (otherwise may be auto from config). |
| `showList` | `bool` | `true` | Show the attached media list. |
| `theme` | `?string` | Configured theme | Override the globally configured theme for this uploader. |
| `maxSizeKb` | `?int` | Active preset's `max_kb` | Max file size (KB). An explicit value overrides the preset. |
| `preset` | `?string` | `null` | Choose a preset (`images`, `docs`, `videos`, `default`, etc.). |
| `allowedTypes` | `array` | `[]` | Extensions filter (e.g. `['jpg','png']`). |
| `allowedMimes` | `array` | `[]` | MIME filter (e.g. `['image/jpeg']`). |
| `onNameConflict` | `string` | `rename` | Strategy: `rename` \| `replace` \| `skip` \| `allow`. |
| `skipExactDuplicates` | `bool` | `false` | Uses SHA-256 stored in `custom_properties->sha256`. |
| `watermark` | `?bool` | Config value | Enable or disable watermarking for this uploader instance. |
| `namespaces` | `array` | `['App\\Models']` | Namespaces for dotted-path resolution. |
| `aliases` | `array` | `[]` | Local alias map, e.g. `['profile' => \App\Models\User::class]`. |
| `attachedFilesTitle` | `string` | `"Attached media"` | Heading text in the list card. |
| `channel` | `?string` | `null` | Route deferred `media:attach` events to a specific uploader. When set, the incoming event must contain the same channel. |
| `listAll` | `bool` | `false` | When `true`, the attached media list shows **all collections**, grouped by collection name (still editable). |
| `authorizeAbility` | `?string` | `null` | Gate/Policy ability checked against the target model before upload/delete/edit/reorder/attach. Required for deferred attachment. See [Authorization](#authorization). |

---

## Events

The component dispatches browser events you can listen for:

- `media:attach` — **incoming** event the component listens for. Arguments: `model` (class/alias), `id`, optional `collection`, optional `disk`, and optional `channel`. A configured channel must match exactly. A different collection is ignored; a different disk is rejected. The initial deferred attachment requires `authorizeAbility` and the configured model class. An already-bound uploader only accepts its existing model and ID. Triggers attaching queued files to the saved target.
- `media-attached` — emitted after a successful `media:attach`. Payload: `{ model: FQCN, id: string, channel: ?string }`.
- `media-uploaded` — emitted after an immediate upload (when a target already exists).
- `media-deleted` — emitted after deletion (`detail.id` contains the Media ID).
- `media-meta-updated` — emitted after inline metadata is saved.

Example:
```html
<div
  x-data
  x-on:media-uploaded.window="console.log('uploaded!')"
  x-on:media-deleted.window="console.log('deleted', $event.detail?.id)"
>
  <livewire:media-uploader :for="$user" collection="images" preset="images" />
</div>
```

---

## Authorization

For an uploader bound to a saved record at mount, `authorizeAbility` is optional. Without it, the component verifies that media belongs to that fixed record, but your app must authorize the user's access before rendering the component. The uploader cannot be retargeted to another record by an attach event.

For deferred uploads (`model` without an `id`), **`authorizeAbility` is required before attachment**. The model and ID passed to `media:attach` are client-controlled input, even when the event is dispatched by a parent Livewire component. A route or create-page policy check cannot authorize those arguments. The uploader checks the configured ability against the selected saved record and returns `403` if no ability is configured or the policy denies access.

Set `authorizeAbility` to a Gate/Policy ability name to enforce authorization before every persistent mutation (`uploadFiles`, `remove`, `saveEdit`, `reorderItems`, and `media:attach`). It uses Laravel's `Gate::authorize()` and works with existing Policies, Gate closures, or permission systems integrated with Gate.

```html
<livewire:media-uploader
    :for="$post"
    collection="images"
    preset="images"
    authorizeAbility="update"
/>
```

With the example above, before any upload/delete/edit/reorder/attach action runs, the component calls the equivalent of `Gate::authorize('update', $post)`. If your `PostPolicy::update()` returns `false`, the action aborts with a `403` instead of proceeding.

**Notes:**
- One ability is checked for all persistent mutations. If media management needs a distinct policy from the model's usual `update` ability, define a dedicated ability (for example, `manageMedia`) and pass that name. Hiding a button or wrapping the Blade component does not authorize individual Livewire actions.
- The `channel` prop routes `media:attach` events to the intended component instance. A configured channel must match exactly, but it is not an authorization boundary.
- The configured deferred model class, collection, and disk cannot be changed through attachment arguments. Set these on the uploader itself.
- When upgrading existing create forms, add `authorizeAbility` and a matching Gate/Policy. Deferred attachment without an ability now returns `403`.

---

> The list view tries `getUrl('thumb')` and falls back to `getUrl()` if no conversion is available.

---

## Overlays & UX Notes

- **Image Preview Overlay** (lightbox): toggled by `x-show="preview.open"`.
- **Delete Confirmation Modal**: toggled by `$wire.confirmingDeleteId !== null`.
- Add once in your layout to prevent flash-of-overlay:
  ```html
  <style>[x-cloak]{ display:none !important; }</style>
  ```
- Z-index defaults: preview `z-[60]`, delete modal `z-50`. Adjust to your stack if you have higher layers.

---

## Troubleshooting

- **“Target model must be saved…”**  
  Ensure the model exists in DB (`$model->exists === true`) before rendering the component.

- **“must implement Spatie\MediaLibrary\HasMedia”**  
  Add `implements HasMedia` + `InteractsWithMedia` to your model.

- **Unknown model class/alias**  
  If using `model="something"` + `:id`, make sure:
    - It’s a valid FQCN, morph alias, or maps via dotted path within `namespaces`, or
    - You passed a local alias via `:aliases="['something' => \App\Models\YourModel::class]`.

- **Deferred attachment returns `403`:** Set `authorizeAbility` and verify that its Gate/Policy permits the current user to manage media on the saved record. The event must use the configured model class and cannot change the disk or switch an already-bound target. See [Upgrading to v0.8.0](#upgrading-to-v080).

- **An attach event is ignored:** Check that the event's collection matches the uploader and that its channel matches exactly when a channel is configured.

- **Multiple files selected, but only one remains after upload**  
  Check the target model's `registerMediaCollections()` method. If the collection uses Spatie Media Library's `->singleFile()`, each new upload replaces the previous media item.

    For multiple files:
    
    ```php
    $this->addMediaCollection('photos');
    ```
    For a collection that should contain only one file:
    ```php
    $this->addMediaCollection('avatar')
      ->singleFile();
    ```

  
- **`accept` not applied**  
  Set `accept_from_config=true` and ensure your preset has `types`/`mimes`. Or override via `accept` prop.

- **No thumbnails**  
  Add a `thumb` conversion (see [Model Setup](#model-setup-spatie-media-library)).

- **Watermark path is missing or unreadable:** Set `MEDIA_UPLOADER_WATERMARK_PATH` to an absolute readable image path, or configure `watermark.path` in the published config. Clear or rebuild Laravel's config cache afterward.

- **A watermarked format fails to process:** Confirm that the driver selected by `media-library.image_driver` supports both the uploaded format and the watermark format. GD is available in the package's CI matrix; Imagick or Vips support depends on the consuming application.

---

## Roadmap

Possible future additions:

- Configurable upload count and total batch-size limits.
- Richer upload event payloads for consuming applications.
- Expiring preview and download URLs for private media.

PRs welcome!

---

## License

**MIT** © CodebyRay (Ray Cuzzart II)

---

**Component aliases:** `media-uploader` and `media.media-uploader`  
**View namespace:** `media-uploader::livewire.media-uploader`
