# Changelog

All notable changes to `laravarc/core` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.6.2] - 2026-10-20

### Fixed

- Feature suffix normalisation now strips a repeated menu prefix (`/menus/features` with menu `/menus` -> `/features`; exactly `/menus` -> `/`) instead of rejecting valid catalog metadata.

[1.6.2]: https://github.com/laravarc/core/releases/tag/v1.6.2

## [1.6.1] - 2026-10-20

### Changed

- `PathValidator` now normalises forgiving admin input instead of rejecting it: missing leading slash, dots to path separators, underscores/spaces to hyphens, camelCase/PascalCase to kebab-case, duplicate slashes, and trailing slashes. Dynamic segments (`{id}` / `:id`) remain invalid.

[1.6.1]: https://github.com/laravarc/core/releases/tag/v1.6.1

## [1.6.0] - 2026-10-20

### Added

- Optional `path` parameter on `Laravarc\Core\Metadata\Attributes\Menu` and `Feature`; compiled metadata carries the value when provided and omits it when null.
- `Laravarc\Core\Metadata\Support\PathValidator` — shared rules for absolute menu paths, relative feature suffixes, slash normalisation, reserved paths, per-surface uniqueness, and primary-tab uniqueness.
- `MetadataCompileResult::$warnings` plus `laravarc:metadata compile` output for nodes that still rely on the derived path fallback.

### Changed

- Path values are additive metadata; existing attribute calls and compiled artifacts without `path` remain valid. No breaking change.

[1.6.0]: https://github.com/laravarc/core/releases/tag/v1.6.0

## [1.5.0] - 2026-10-20

### Removed

- `visibilityAbility` parameter from `Laravarc\Core\Metadata\Attributes\Feature`.
- Automatic default `visibility_ability: 'viewAny'` emission in `ReflectionMetadataReader`; compiled Feature metadata no longer carries `visibility_ability`.

### Changed

- `Feature` metadata is now limited to UI placement + entitlement catalog data; authorization/visibility ability linkage is no longer stored on Feature metadata.
- Metadata compilation tests no longer assert the removed `visibility_ability` field.

[1.5.0]: https://github.com/laravarc/core/releases/tag/v1.5.0

## [1.4.0] - 2026-10-12

### Changed

- `laravarc:seed` (non-dry-run) streams progress **per seeder as it runs** via `ModuleSeeder` `onProgress` callback, instead of buffering all lines until the batch finishes. `--dry-run` final-order listing and `SeedPriority` sort semantics unchanged.

### Fixed

- Authorization: a method's own policy target now takes precedence over the module-wide `ability_overrides` map, so modules with more than one policy/controller no longer have one controller's declared model shadow another's ability. `ability_overrides` remains as a fallback when a requirement declares no model.
- Authorization: `PolicyTargetResolver` now selects the route parameter matching the target model's `getRouteKeyName()` (typically `id`) instead of always taking the first scalar parameter, so nested routes such as `fixed-assets/{faId}/adjustments/{id}` resolve the correct instance.

[1.4.0]: https://github.com/laravarc/core/releases/tag/v1.4.0

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
