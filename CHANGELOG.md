# Changelog

All notable changes to `laravarc/core` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-09-05

### Added

- Modular CRUD generation with Command/Query service split, contract sync, and metadata-driven authorization.
- Optional integration with `laravarc/eventer` for transport-agnostic domain events.
- Metadata-compiled listener registration via attributes and `CoreListenerRegistrar`.
- Module discovery, schema-driven generation, presentation stacks (API / Blade), and extension hooks.
- Canonical Artisan prefix `laravarc:` with shorthand aliases `larc:`.

### Fixed

- `laravarc:contract sync` emits `use` imports for class types and preserves existing Shared contract imports and method PHPDoc.

[1.0.0]: https://github.com/laravarc/core/releases/tag/v1.0.0
