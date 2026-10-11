# Changelog

All notable changes to `laravarc/core` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.0] - 2026-10-11

### Added

- Cross-module `#[Feature(menu: '<key>')]` references: a feature may point at a menu key owned by any module. Cross-module references remain flat on the declaring module's `features` list with the `menu` field retained, so surface modules no longer need a duplicate `#[Menu]` merely to compile.
- Compile-time validation that every feature `menu` reference resolves to a menu key present in **any** compiled module; unresolved references raise `MetadataCompileException` naming the feature and menu key.

### Changed

- `ReflectionMetadataReader::nestFeatures()` only nests features whose `menu` key is owned by the same module. References to another module's menu are no longer treated as unknown-menu errors and are emitted at module level with `menu` retained.
- `CoreMetadataCompiler::compile()` validates feature→menu references against the global menu-key set (all compiled modules) after module compilation, instead of failing per-module.
- Per-module duplicate menu key detection (`assertNoDuplicates`) is unchanged.

[1.3.0]: https://github.com/laravarc/core/releases/tag/v1.3.0

## [1.1.0] - 2026-09-10

### Added

- Optional `Laravarc\Core\Metadata\Attributes\SeedPriority` attribute for module seeders.
- `laravarc:seed` flattens discovered `*Seeder` classes (or `--module=` scope) into one list, then sorts: attributed by priority **DESC** (tie = FQCN ASC), then unattributed FQCN ASC. Larger priority = earlier (z-index). Duplicate priorities allowed.
- `--dry-run` prints seeders in **final global execution order**.

### Changed

- `ModuleSeeder` no longer runs seeders in per-module filesystem / alpha-only order.

[1.1.0]: https://github.com/laravarc/core/releases/tag/v1.1.0

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
