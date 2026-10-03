# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

> **Versioning policy:** Until `1.0.0`, minor bumps (e.g., `0.1 → 0.2`) may include breaking changes. Patch releases in the same minor (e.g., `0.1.x`) are bug fixes only.

---

## [Unreleased]

## [v0.8.0] — 2026-10-03

### Added
- Regression coverage for attachment authorization, morph aliases, custom media models, queue ordering, replacement failures, and both supplied themes.

### Security
- Deferred attachment requires a configured Gate/Policy ability and authorizes the selected record before binding it.
- Attach events cannot retarget an already-bound uploader, change its deferred model class, or override its configured collection or disk.
- The expected deferred model class is locked against client-side mutation.

### Fixed
- Preserve existing media until replacement storage succeeds, including single-file collections and failed destination writes.
- Save captions, descriptions, duplicate hashes, and ordering with the new media record before replacement cleanup.
- Resolve media ownership through the target's relationship, supporting morph aliases and custom Media Library models for edits, deletes, and reordering.
- Append reordered queued uploads after existing media in the active collection instead of resetting their order to one.
- Preserve the original Media Library display name when staging uploaded files.

### Upgrade Notes
- Deferred uploaders must now set `authorizeAbility` and provide a policy that permits the current user to manage media on the saved target. Existing-record uploaders may continue to rely on application authorization of their fixed target.
- Configure `collection` and `disk` on the uploader. Optional attach-event arguments can confirm these settings but can no longer override them. A mismatched collection is ignored; a mismatched disk is rejected with `403`.

## [v0.7.0] — 2026-09-25

### Added
- Optional image watermarking before storage, with global configuration and a per-uploader `watermark` override.
- Full watermark and package-configuration documentation.
- Explicit `spatie/image` v3 dependency for the image-processing API used by the package.
- Dependency security auditing in CI.

### Changed
- Staged uploads through a local temporary file so watermarking and Media Library storage work when Livewire uses remote temporary storage.
- Preset `max_kb`, `types`, and `mimes` values now remain overridable per uploader while correctly supplying defaults.
- Media Library v11 is now the supported major version; the previous v10 constraint could not coexist with the supported Laravel versions.
- Raised the supported Livewire minimums to patched releases `3.8.3` and `4.3.4`.
- Expanded the CI matrix to PHP 8.5.

### Fixed
- Watermark failures occur before `replace` conflict handling, preserving existing media when image processing fails.
- Server-controlled authorization and upload-policy properties are locked against client-side Livewire mutation.
- Deferred uploaders with a configured channel now ignore attach events that omit that channel.

---

## [v0.6.0] — 2026-07-25

### Added
- **Drag-and-drop media ordering.** Added native HTML5 drag-and-drop reordering to both Tailwind and Bootstrap themes for attached media and the pending upload queue.
    - Supports inserting items before or after the current target, including moving items to the end of the list.
    - Displays a visual drop-position indicator while dragging.
    - Persists attached-media ordering using Spatie Media Library's `order_column`.
    - Supports reordering pending uploads before they are saved.
    - Keeps `list-all` ordering scoped to the individual media collection.
    - Dispatches a `media-reordered` event after persisted media is reordered.
    - Uses the existing Alpine.js integration with no additional JavaScript dependency.
- **Clickable thumbnail previews.** Image thumbnails for pending uploads and attached media can now be clicked to open the existing preview overlay.

### Changed
- Updated documentation to clarify that `multiple` controls file selection but does not override Spatie Media Library's `singleFile()` collection behavior.
- Updated image conversion examples to use `Spatie\Image\Enums\Fit` for compatibility with current Spatie Image releases.

## [v0.5.0] — 2026-07-20
### Added
- **Optional authorization hook (`authorizeAbility`)**: Pass a Gate/Policy ability name and the component will call `Gate::authorize()` against the resolved target model before `uploadFiles`, `remove`, `saveEdit`, and the `media:attach` handler run. Unset by default — fully backward-compatible, opt-in only. See README [Authorization](README.md#authorization) section.
- **CI**: GitHub Actions workflow testing the PHP × Laravel × Livewire support matrix on every push/PR.

### Changed
- **Dropped Laravel 10 and 11 support.** Both are past their security-support window (Laravel 10 EOL; Laravel 11 security support ended March 2026) and current releases in both lines carry unpatched advisories that block a fresh `composer install` under Composer's default audit policy — confirmed via CI, not a judgment call. If you're on Laravel 10/11, stay on `v0.4.x` and prioritize a Laravel upgrade.
- **Raised minimum PHP to 8.2** to match what's actually installable with supported Laravel/Spatie Media Library versions.
- Widened `pestphp/pest` and `pestphp/pest-plugin-laravel` dev constraints to also allow `^4.0`, which is what unlocks Laravel 13 test coverage (`pest-plugin-laravel` didn't support Laravel 13 until its v4.1.0 release).

### Fixed
- Corrected README/composer.json copy that said "Livewire v3" despite `^3.0 || ^4.0` already being the supported range.

---

## [v0.4.0] — 2026-07-15

### Added
- **`NameConflictStrategy` Enum**: Introduced a strongly-typed Enum for handling name conflicts (`RENAME`, `REPLACE`, `SKIP`, `ALLOW`), replacing raw string checks.
- **Custom `ModelResolutionException`**: Replaced generic `abort()` calls with a dedicated exception for clearer debugging when model binding fails.

### Changed
- **Configurable Namespaces**: The component now resolves models using the `model_namespaces` configuration array instead of hardcoded `App\Models` strings, allowing for better support in modular/domain-driven architectures.
- **Validation Refactor**: Centralized upload validation into a dedicated `rules()` method, simplifying the `uploadFiles()` logic and improving maintainability.

### Fixed
- Improved exception handling in test suite to correctly capture and assert custom package exceptions.
- Resolved potential `MassAssignmentException` issues in tests when instantiating standard User models.
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
- **v0.8.0:** Deferred attachment now requires `authorizeAbility` and a matching Gate/Policy. Configure the target model class, collection, and disk on the uploader; attach events can no longer override these settings or retarget an already-bound uploader. See the README's [v0.8.0 upgrade guide](README.md#upgrading-to-v080).
- **v0.7.0:** Upgrade Livewire to `3.8.3+` or `4.3.4+` and Spatie Media Library to v11. These minimums guarantee the image API used for watermarking and exclude Livewire releases affected by CVE-2026-81887.
- To get thumbnail previews, add a `thumb` conversion on your model or adjust the view to your conversion names.
- For single-file collections (e.g., `avatars`), declare the collection in your model and call `->singleFile()`; the component’s `multiple=false` only affects the input, not backend replacement.

---

[Unreleased]: https://github.com/codebyray/livewire-media-uploader/compare/v0.8.0...HEAD
[v0.8.0]: https://github.com/codebyray/livewire-media-uploader/compare/v0.7.0...v0.8.0
[v0.7.0]: https://github.com/codebyray/livewire-media-uploader/compare/v0.6.0...v0.7.0
[v0.6.0]: https://github.com/codebyray/livewire-media-uploader/compare/v0.5.0...v0.6.0
[v0.5.0]: https://github.com/codebyray/livewire-media-uploader/releases/tag/v0.5.0
[v0.4.0]: https://github.com/codebyray/livewire-media-uploader/releases/tag/v0.4.0
[v0.3.0]: https://github.com/codebyray/livewire-media-uploader/releases/tag/v0.3.0
[v0.2.0]: https://github.com/codebyray/livewire-media-uploader/releases/tag/v0.2.0
[v0.1.0]: https://github.com/codebyray/livewire-media-uploader/releases/tag/v0.1.0
