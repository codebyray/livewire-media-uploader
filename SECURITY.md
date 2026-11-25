# Security policy

Security fixes target the newest release candidate and, after launch, the latest stable `1.x` release. Earlier `0.x` versions are not guaranteed backports. Keep Laravel, Livewire, Spatie, and their transitive dependencies patched and run `composer audit` in the consuming application.

Report suspected vulnerabilities privately to **dev@codebyray.com**, the maintainer contact listed in `composer.json`. Include affected versions, a minimal reproduction, expected impact, and any safe workaround. Do not include credentials, customer uploads, or personal data. Avoid public issues for an undisclosed vulnerability.

Reports are handled on a best-effort basis without a guaranteed response time. Authorization of application records remains the consuming application's responsibility; deferred attachment also requires the uploader's configured Gate/Policy ability. See [the API contract](docs/API.md#authorization-and-targeting).
