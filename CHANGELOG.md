# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

> **Versioning policy:** Until `1.0.0`, minor bumps (e.g., `0.1 → 0.2`) may include breaking changes. Patch releases in the same minor (e.g., `0.1.x`) are bug fixes only.

---

## [Unreleased]
### Added

### Changed

### Fixed

---
## [v0.3.0] — 2025-09-01

### Added
- **Deferred uploads on create**: You can now queue files before the target model exists and attach them after save by dispatching the `media:attach` event. The uploader accepts `model="post"` (no `id`) on create screens, holds the queue + per-file meta, and attaches once you dispatch. Emits `media-attached` when done.
- **`list-all` view**: Set `:list-all="true"` to show **all collections** for the current model in a single list, grouped by collection name. Items remain fully editable (caption/description/order).
- **Tailwind dark mode docs/snippets**: Added guidance and examples for enabling global light/dark mode with the Tailwind theme.

### Changed
- Graceful “no target yet” behavior on create screens:
    - `nextOrder()` now derives order from the local queue if the model isn’t saved yet.
    - `uploadFiles()` **queues** when there’s no target (instead of erroring) and flashes: _“Files queued. They will be attached after you save.”_

### Fixed
- N/A
---
## [v0.2.0] — 2025-09-01

### Added
- **Theme system** with **Tailwind (default)** and **Bootstrap** themes.
- **Custom themes** support:
    1. Create a new folder under `resources/views/vendor/media-uploader/themes`, e.g. `custom/`.
    2. Copy `media-uploader.blade.php` from `tailwind/` or `bootstrap/` into `custom/` (keep the filename).
    3. Register in config:
       ```php
       'themes' => [
           'tailwind'  => 'media-uploader::themes.tailwind.media-uploader',
           'bootstrap' => 'media-uploader::themes.bootstrap.media-uploader',
           'custom'    => 'media-uploader::themes.custom.media-uploader',
       ],
       'theme' => 'custom', // to make it default
       ```
    4. Or set per-instance:
       ```html
       <livewire:media-uploader :for="$post" collection="images" theme="custom" />
       ```
- Configuration docs for each option in `config/media-uploader.php`, including **ENV overrides**, presets (`types`, `mimes`, `max_kb`), and **collection → preset** mapping.

### Changed
- Default view now resolves via the **theme map** (Tailwind by default).  
  Existing installs continue to render with Tailwind unless you switch themes.

### Compatibility
- **No breaking changes.** Defaults preserve prior behavior.
- If you previously published the old (pre-theme) Blade, it will keep working if you’ve retained the legacy alias. If you want to use the new theme system, publish/move your override to `themes/<your-theme>/media-uploader.blade.php`.

### Migration Notes (only if you customized the old path)
- Minor migration required for users who published the old view (move file to the themed path).
- Move your customized Blade from:
    ```html
    resources/views/vendor/media-uploader/livewire/media-uploader.blade.php
    ```
  to:
    ```html
    resources/views/vendor/media-uploader/themes/tailwind/media-uploader.blade.php
    ```
  (or into your custom theme folder), and register that theme in the config.

## [v0.1.0] — 2025-08-30
### Added
- **Livewire v3** media uploader component.
- **Tailwind-only publishable Blade** view with Alpine-powered image preview overlay and delete confirmation modal.
- **Spatie Laravel Media Library** integration:
    - Attach/list/delete media within a configurable **collection** (e.g., `images`, `avatars`, `photos`).
    - Per-file **metadata** (caption, description, order).
    - Optional **thumbnail** usage (`getUrl('thumb')`) with graceful fallback.
- **Drag & drop** uploads with progress indicator.
- **Validation presets** via config (types, mimes, max size) with collection→preset mapping and optional auto-`accept` attribute.
- **Name-conflict strategies:** `rename`, `replace`, `skip`, `allow`.
- **Exact duplicate** detection (SHA-256) with `skipExactDuplicates`.
- Flexible **model resolution**:
    - `:for="$model"` (saved instance),
    - `model="user" :id="1"` (short name + id),
    - FQCN, morph map alias, or dotted paths with custom namespaces and local aliases.
- **Events** for UX integrations:
    - `media-uploaded`, `media-deleted` (with `id`), `media-meta-updated`.
- **Publishable config** (`media-uploader.php`) and **view** (`livewire/media-uploader.blade.php`).
- **Test suite** (Pest + Testbench) with in-memory SQLite and fake disks.

---

## Deprecations Policy
- Any deprecations will be noted here and kept for at least one subsequent minor (e.g., deprecate in `0.3.x`, remove in `0.4.0`). After `1.0.0`, deprecations will be removed in the next **major** release.

---

## Upgrade Notes
- To get thumbnail previews, add a `thumb` conversion on your model or adjust the view to your conversion names.
- For single-file collections (e.g., `avatars`), declare the collection in your model and call `->singleFile()`; the component’s `multiple=false` only affects the input, not backend replacement.

---

[Unreleased]: https://github.com/codebyray/livewire-media-uploader/compare/v0.2.0...HEAD
[v0.1.0]: https://github.com/codebyray/livewire-media-uploader/releases/tag/v0.1.0
