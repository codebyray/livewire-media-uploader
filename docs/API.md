# Public API and support policy

## Release status

`v1.0.0-rc.1` is a prerelease for integration testing. It is not the stable `v1.0.0` tag. Any adjustments made during the RC period will be documented in the changelog and upgrade instructions.

## Compatibility surface

The intended stable API consists of:

- The `media-uploader` and `media.media-uploader` component aliases and the props documented in the README.
- The keys and accepted values in the published `media-uploader.php` configuration.
- Documented incoming/outgoing event names, payload keys, and completion/failure semantics.
- The published view namespace, theme map, and Livewire bindings used by the shipped views.
- The metadata custom properties `caption`, `description`, and optional `sha256`, plus Media Library's `order_column`.

Undocumented helpers, implementation classes, temporary paths, exact notice wording, and the test harness are internal. Consumer code should use the documented props/events rather than call internal helpers. Custom views must preserve the shipped Livewire bindings and merge documented template changes when upgrading.

After stable `1.0.0`, backwards-incompatible changes to this surface require a major release. Minor releases may add optional props, configuration, or payload fields. Patch releases correct behavior and may raise dependency patch minimums to exclude security issues. Runtime/framework major support removals will be announced and handled as a breaking change.

## Authorization and targeting

For a saved target fixed at mount, the application must authorize access to that record. Set `authorizeAbility` to recheck a Gate/Policy on every persistent mutation: upload, edit, delete, reorder, and attach. The component's media ownership checks are not a substitute for user authorization.

Deferred attachment requires `authorizeAbility`. The event's model must resolve to the configured deferred class, and the selected saved record must pass the policy before binding. A bound uploader cannot switch records. Collection arguments that differ from the configured collection are ignored; disk overrides are rejected. A configured channel must match exactly. Channels route events; they do not authorize users.

## Uploads and failures

Validation runs on the batch before persistence. Authorization runs before persistence. Their failures use the usual Livewire validation errors or Laravel `403` responses, without a processing-failure event.

Files are then processed sequentially. A fully successful batch dispatches `media-uploaded` with no payload. Skipped duplicate/name conflicts count as handled files. A per-file preparation, watermark, or storage failure stops the batch, reports the exception to Laravel, adds an inline error, and dispatches `media-upload-failed` with `collection`, configured `channel`, and integer `queueKey`.

Completed and skipped files leave the queue. Failed and unattempted files keep their metadata. Queue keys can be sparse after a failure; use `selected[*].queue_key` when binding custom queue controls. Removing/reordering files clears stale queue errors. Retrying processes the remaining queue, not the earlier successful files. Successful records remain visible even when a later file fails: a batch is not an all-or-nothing transaction.

An interrupted attempt's new Media Library row/files are cleaned up where possible, without touching earlier completed uploads. Cleanup failures are also reported. Storage/conversion/event listeners supplied by a consuming application may have external effects that cannot be rolled back; review logs after such failures. If replacement storage succeeded but old-file cleanup failed, the new file is not requeued and the UI requests administrator attention.

An existing replacement is kept until the new file and its metadata have been stored. Spatie still controls `singleFile()` and collection-size behavior. This package cannot roll back third-party side effects, restore files deleted outside its control, or guarantee exactly-once behavior if a request's response is lost. Name-conflict checks are not a cross-request lock; simultaneous uploads may require application-level serialization.

For deferred creation, `media-attached` fires once after all queued work completes (or when initially attaching an empty queue), following `media-uploaded` when files were processed. Payload: `model`, string `id`, configured nullable `channel`. A partial failure does not emit completion; a later successful retry does. Replaying an already-completed attach event is a no-op for completion notification.

## Filenames, storage, and watermarking

Conflict checks use the same default sanitization and configured `file_namer.originalFileName()` behavior as Media Library. Filename normalization is used for comparison; the original candidate is passed to Media Library so custom namers are not applied twice. Custom namers must be deterministic, and `rename` requires suffixes to produce distinct stored names. A constant-name mapping fails safely. Existing filenames and media display names are not rewritten by upgrades.

Temporary storage and the final Media Library disk are independent. Files are read as streams into a local staging file, which requires writable local temporary space. Local/default and separate temporary disks have integration coverage; a stream-only source has unit coverage. Live S3/MinIO service behavior is not certified by these local tests. Follow Livewire's direct-to-S3 restrictions (including its restriction on multiple temporary uploads); prefer local temporary uploads with an S3 destination for multi-file forms. Private disks need an application's own authorized URL delivery; the bundled list uses ordinary `getUrl()` URLs.

Watermarking changes the stored original and any conversions generated from it. Non-image uploads are unchanged. The configured image driver must support the source and watermark formats. GD is tested, including the shipped percentage sizing, padding, transparency, and opacity; Imagick/Vips and animated-image preservation require validation in the consuming application.

## Support

The planned stable maintenance line is the latest `1.x` release, using the runtime/framework ranges in `composer.json`. RC users should upgrade to the newest candidate or stable release when available. Bug fixes and security updates target the current maintained line; older `0.x` releases are not promised ongoing backports.

Support is best-effort without a response-time guarantee. Report reproducible bugs through GitHub issues with the package/PHP/Laravel/Livewire/Media Library versions, theme, temporary/destination disks, and a minimal example. See [SECURITY.md](../SECURITY.md) for private security reporting.
