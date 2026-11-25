# Testing and release verification

## PHP tests

```bash
composer install
composer test
composer validate --strict
composer audit --locked
```

The package intentionally does not commit its development `composer.lock`. A fresh checkout resolves the declared ranges. CI resolves each Laravel/Livewire combination using temporary `composer update --with` constraints, installs that lock, audits it, and runs the suite across PHP 8.2–8.5, excluding Laravel 13 on PHP 8.2.

## Browser tests

The development-only Playwright app uses Testbench, SQLite, bundled Livewire/Alpine, locally built Tailwind CSS, and Bootstrap CSS. No CDN or application credentials are needed. Node 22+, PHP with GD/SQLite, and the Composer development dependencies are required.

```bash
npm ci
npx playwright install chromium
npm run test:browser
```

To use an existing local Chrome installation instead of downloading Chromium:

```bash
PLAYWRIGHT_CHROME_CHANNEL=chrome npm run test:browser
```

Playwright starts its own loopback-only server on port 8123, builds the test CSS, and stores runtime data under ignored `tests/Browser/.runtime/`. The six scenarios cover keyboard selection, real upload progress events, pending/attached drag reordering, previews and focus, metadata edits, delete confirmation, multiple instances, dropped files, and deferred failure/retry behavior in both themes. CI runs the browser suite on Laravel 12/Livewire 3 and Laravel 13/Livewire 4 with Chromium.

The browser app is only a test fixture: never deploy `tests/Browser/server.php` or its intentionally permissive test policy. Test results and failure traces are ignored locally and uploaded on CI failure. Node dependencies are not required to use this PHP package.

Git distribution archives exclude the test harness, browser tooling, and CI files through `.gitattributes`. Use a source checkout to contribute or run the tests.

## v1.0.0-rc.1 checklist

- [x] Fix normalized filename conflicts and cover repeated names plus configured file namers.
- [x] Make partial failures retryable without repeating earlier successful files.
- [x] Prevent deferred completion/redirect events until retries finish.
- [x] Cover interrupted-attempt cleanup, a separate temporary disk, and shipped watermark settings.
- [x] Run the local PHP suite and all six browser scenarios with patched dependencies.
- [x] Document candidate installation, failure semantics, compatibility scope, and private security reporting.
- [ ] Require all PHP matrix and browser CI jobs to pass on the release PR.
- [ ] Merge the reviewed branch and tag the reviewed commit `v1.0.0-rc.1`; mark its GitHub release as a prerelease.

Composer derives the package version from Git tags. Do not add a hard-coded `version` field or lower the package/application's global `minimum-stability` to publish or evaluate this candidate.

## Before stable v1.0.0

- Validate the candidate in a consuming application's saved-record and deferred-create flows, including its actual policies and any custom/published views.
- Test that application's actual destination/temporary disks, file formats, conversions, and queued workers. Test private-media delivery and S3 restrictions explicitly if those are used.
- Resolve RC feedback, run a fresh dependency audit, and rerun the full CI matrix.
- Document any RC API adjustments, prepare the stable changelog and upgrade guide, then tag the reviewed stable commit.

These are release gates; passing the local fixture suite does not certify every storage service, image driver, custom policy, or conversion hook.
