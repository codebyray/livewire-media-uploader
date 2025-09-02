# Livewire Media Uploader

Livewire Media Uploader is a reusable Livewire v3 component that integrates seamlessly with Spatie Laravel Media Library. It ships a clean Tailwind Blade view by default (fully publishable), Bootstrap theme as an option, Alpine overlays for previews/confirmations, drag-and-drop uploads, per-file metadata (caption/description/order), configurable presets, name-conflict strategies, and optional SHA-256 duplicate detection. Drop it in, point it at a model, and you’re shipping in minutes.

---

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Publishing Assets](#publishing-assets)
- [Theme System](#theme-system-tailwind--bootstrap--custom)
  - [Dark Mode - Tailwind](#dark-mode-tailwind-theme)
  - [Custom Theme](#custom-themes)
- [Quick Start](#quick-start)
- [Usage Examples](#usage-examples)
  - [Create flow (deferred uploads)](#create-flow-deferred-uploads)
- [Configuration](#configuration)
- [Props](#props)
- [Events](#events)
- [Model Setup (Spatie Media Library)](#model-setup-spatie-media-library)
- [Overlays & UX Notes](#overlays--ux-notes)
- [Troubleshooting](#troubleshooting)
- [Roadmap](#roadmap)
- [License](#license)

---

## Features

- ✅ Livewire v3 component with themeable Blade UI
  - Tailwind (default)
  - Bootstrap (optional)
  - Fully publishable and overridable
- ✅ Spatie Media Library integration (attach, list, edit meta, delete)
- ✅ **Publishable view** for per-project customization
- ✅ Drag & drop uploads + progress bar
- ✅ Inline edit of **caption / description / order**
- ✅ Name-conflict strategies: **rename | replace | skip | allow**
- ✅ Optional **exact duplicate** detection via SHA-256
- ✅ Collection → preset mapping (auto `accept` attribute)
- ✅ Image preview **overlay** + delete confirmation **modal**
- ✅ Works with:
    - Saved model instance (`:for="$model"`)
    - String model + id (`model="user" :id="1"`)
    - FQCN, morph map alias, or dotted paths with custom namespaces
    - Local alias map

---

## Requirements

- PHP **8.1+**
- Laravel **10.x | 11.x | 12.x**
- Livewire **^3.0**
- spatie/laravel-medialibrary **^10.12**
- TailwindCSS (optional but recommended for the default view)
- Alpine.js (used by overlays/progress; see [Overlays & UX Notes](#overlays--ux-notes))
- CSS depending on theme:
  - Tailwind theme → TailwindCSS (recommended)
  - Bootstrap theme → Bootstrap CSS (no Bootstrap JS required; Alpine drives modals)

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
```

### Notes
- Values are comma-separated; spaces are OK (the package trims them).
- After changing .env, run:
```bash
- php artisan config:clear
# (or) php artisan config:cache
```
- The `````<input accept="…">````` attribute is auto-filled from the active preset when accept_from_config is true (default). You can still override it per-component with the accept prop.
- If uploads fail due to size, make sure your PHP/Server limits also allow it (e.g. upload_max_filesize, post_max_size).

---

## Quick Start

1) Ensure your target Eloquent model implements `Spatie\MediaLibrary\HasMedia` and is **saved**.

    #### Model Setup (Spatie Media Library)
    
    Your model must implement `HasMedia` and be **saved** before attaching media.
    
    ```php
    use Spatie\MediaLibrary\HasMedia;
    use Spatie\MediaLibrary\InteractsWithMedia;
    use Spatie\MediaLibrary\MediaCollections\Models\Media;
    
    class Post extends Model implements HasMedia
    {
        use InteractsWithMedia;
    
        public function registerMediaCollections(): void
        {
            $this->addMediaCollection('photos')        // matches collection="photos"
                ->useDisk('public')                    // or 's3'
                ->withResponsiveImages();             // optional
    
            // If you also have avatars somewhere:
            $this->addMediaCollection('avatars')->singleFile();
        }
    
        public function registerMediaConversions(Media $media = null): void
        {
            $this->addMediaConversion('thumb')
                ->fit('contain', 256, 256)
                ->performOnCollections('photos', 'avatars') // scope to specific collections
                ->nonQueued();
        }
    }
    ```
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
/>
```
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
        $this->dispatch('media:attach', model: 'post', id: $post->id, collection: 'images');
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
- After you persist the model, dispatch:
    ```php
    $this->dispatch('media:attach', model: 'post', id: $post->id, collection: 'images');
    ```
- The uploader resolves the saved target, attaches any queued files, and emits media-attached.
---

## Configuration

The package merges `config/media-uploader.php`:

- `accept_from_config` — if `true`, auto-fills `<input accept>` from the selected preset
- `collections` — map collection name → preset key
- `presets.*.types` — extensions (comma-separated)
- `presets.*.mimes` — MIME types (comma-separated)
- `presets.*.max_kb` — max file size per file in KB

Example:
```php
'collections' => [
    'avatars'     => 'images',
    'images'      => 'images',
    'attachments' => 'docs',
],
```
Show all collections together (grouped)
Set `:list-all="true"` to render a grouped list of **every collection** on the target model. Items stay fully editable.

```html
<livewire:media-uploader
    :for="$post"
    :list-all="true"
    :showList="true"
/>
```
The component decides the active preset in this order:
1. Explicit `$preset` prop
2. Mapping from `collections`
3. Fallback to `default`

---

## Props

| Prop | Type | Default | Description |
|---|---|---|---|
| `for` | `Model` | — | Saved Eloquent model instance implementing `HasMedia`. |
| `model` | `string` | — | Model resolver: alias, FQCN, morph alias, or dotted path. |
| `id` | `int|string` | — | Target model id (used with `model`). |
| `collection` | `string` | `images` | Media collection name. |
| `disk` | `?string` | `null` | Storage disk (e.g. `s3`). |
| `multiple` | `bool` | `true` | Toggle multi-file input. |
| `accept` | `?string` | `null` | `<input accept>` override (otherwise may be auto from config). |
| `showList` | `bool` | `true` | Show the attached media list. |
| `maxSizeKb` | `int` | `500` (overridden to preset’s `max_kb` if empty) | Max file size (KB). |
| `preset` | `?string` | `null` | Choose a preset (`images`, `docs`, `videos`, `default`, etc.). |
| `allowedTypes` | `array` | `[]` | Extensions filter (e.g. `['jpg','png']`). |
| `allowedMimes` | `array` | `[]` | MIME filter (e.g. `['image/jpeg']`). |
| `onNameConflict` | `string` | `rename` | Strategy: `rename` \| `replace` \| `skip` \| `allow`. |
| `skipExactDuplicates` | `bool` | `false` | Uses SHA-256 stored in `custom_properties->sha256`. |
| `namespaces` | `array` | `['App\\Models']` | Namespaces for dotted-path resolution. |
| `aliases` | `array` | `[]` | Local alias map, e.g. `['profile' => \App\Models\User::class]`. |
| `attachedFilesTitle` | `string` | `"Current gallery"` | Heading text in the list card. |
| `listAll` | `bool` | `false` | When `true`, the attached media list shows **all collections**, grouped by collection name (still editable). |

---

## Events

The component dispatches browser events you can listen for:

- `media:attach` — **incoming** event the component listens for. Arguments: `model` (class/alias), `id`, optional `collection`, optional `disk`. Triggers attaching of any queued files to the now-saved target.
- `media-attached` — emitted after a successful `media:attach`. Payload: `{ model: FQCN, id: string }`.
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

- **`accept` not applied**  
  Set `accept_from_config=true` and ensure your preset has `types`/`mimes`. Or override via `accept` prop.

- **No thumbnails**  
  Add a `thumb` conversion (see [Model Setup](#model-setup-spatie-media-library)).

---

## Roadmap

- Drag-to-reorder (update `order_column`).
- Show document icon instead of thumbnail in Attached media list if the file is not an image.

PRs welcome!

---

## License

**MIT** © CodebyRay (Ray Cuzzart II)

---

**Component aliases:** `media-uploader` and `media.media-uploader`  
**View namespace:** `media-uploader::livewire.media-uploader`
