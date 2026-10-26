# Changelog

All notable changes to `laravarc/core` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.4.0] - 2026-10-26

### Added

- **`SurfaceRef::fromMenuKeys` / `fromFeatureKeys` / `fromMenuAndFeatureKeys`** — batch factories for typed surfaces.
- **`AbilityRef::toSortedCompounds`** — unique sorted compound strings from ability refs.
- **`CatalogQuery`:** `revision()`, `surfaceRefs()`, **`policyKeys()`** (scoped manifest),
  `abilityCompoundsToGrant`, `abilityCompoundsForCeiling`, `groupedAbilityRefs()`.

## [2.3.0] - 2026-10-26

### Added

- **`CatalogAuthorizationIndex`:** `abilitiesToGrant`, `abilityCeiling`, `surfacesForPolicies`,
  `identifiersForSurfaces`, `menuKeysForAbilities`, `groupedByMenuKey`.
- **`AbilityRef::toCompound()`** as the compound serializer (parse only via `fromCompound`).
- **`CatalogQuery`:** `filterKnownFeatures`, `unknownFeatures`, `menuKeysForFeatures`.

### Changed

- **BREAKING:** **`Catalog::forArea()`** uses **exact** area match. Rows with `area = null` appear on
  `Catalog::all()` only, not inside `forArea('admin'|'user')`.

## [2.2.1] - 2026-10-26

### Changed

- **`#[Menu]`** removed unused `visible`; added optional **`area`** (compile → catalog sync/manifest).
- **`#[Feature]`** removed unused `description`; added optional **`area`**.

## [2.2.0] - 2026-10-26

### Added

- Nullable **`area`** on `larc_menus` and `larc_features`; **`Catalog::forArea()`** /
  **`CatalogQuery::forArea()`** filter manifest rows (`null` area = visible in every area).

### Changed

- **`Laravarc\Core\Facades\Catalog`** is now an Illuminate **Facade** over
  `CatalogManager` (mockable via `Catalog::swap()` / partial mocks in tests). No
  `extra.laravel.aliases` entry — import the facade explicitly.
- **Catalog static entry** moved from `Laravarc\Core\Catalog\Catalog` to
  `Laravarc\Core\Facades\Catalog` (aligned with sibling Laravarc packages). The old
  class remains as a deprecated forwarder.
- `bootstrap/cache/laravarc/catalog-state.php` now nests install metadata under a
  top-level `catalog` key. Readers accept legacy flat root keys until the next
  write, bump, or `markInstalled`.

## [2.1.0] - 2026-10-26

### Added

- **Catalog authorization index** at `bootstrap/cache/laravarc/catalog-authorization.php`:
  code-derived controller → method → OR-group → `AbilityRef` trees, policy→surface
  join, and menu/feature keys from compiled metadata plus the gate catalog. DB
  catalog columns (custom menus, labels, status, `parent_key`, translations) merge
  at read time through `CatalogReader`; the index file must not freeze row content.
- **`Catalog::all()->authorization()`** (and `CatalogQuery::authorization()`)
  returns the authorization index API (`tree()`, `abilitiesForSurfaces()`).

### Changed

- **`abilitiesForSurfaces()`** returns only the **first OR-group** per gated method
  (gate-catalog declaration order). **`tree()`** keeps every OR-group with no
  flatten. This fixes semantic over-grant versus legacy app `SurfaceCatalog`, which
  flattened all OR-groups into one ability set.
- **`laravarc:cache build`** compiles the authorization index after metadata and
  ability catalogs; build exits non-zero when index compile fails and does not run
  catalog DB sync. **`laravarc:cache clear`** removes `catalog-authorization.php`
  and leaves `catalog-state.php` intact.
- **`CatalogMetadataSurfaceIndex`** policy/model join delegates to
  `CatalogAuthorizationIndex` (no runtime `strrpos` gate walk).
- In **`APP_ENV=production`**, a missing or invalid authorization index throws
  (`MetadataCompileException` family) and does not write a substitute file. In
  **`local`** or **`testing`**, a missing file may compile an in-request fallback
  from metadata plus the ability catalog; fallback failure still throws.

## [2.0.0] - 2026-10-25

### Added

- **Catalog module** under `Laravarc\Core\Catalog`: installed signal at
  `bootstrap/cache/laravarc/catalog-state.php`, `laravarc:catalog` install/uninstall/reset/status,
  metadata sync into `larc_*` catalog tables, dual-backend `CatalogReader`, `Catalog` facade
  (`forUser`, `all`/`query`, chain `forPolicy`/`forModel`/`withOrphaned`/`onlyOrphaned`, terminals
  `manifest`/`resources`/`menus`/`features`/`toArray`), orphan tracking via `is_orphaned` and
  `orphaned_at`, and `laravarc:cache build` catalog sync (`--no-catalog-sync`,
  `LARAVARC_CATALOG_SYNC`). Controller mapping stays on compiled metadata
  (`controllersForMenu`). Catalog uninstall does not modify `larc_user_abilities`. HTTP catalog
  routes remain application-owned; SimpleAcc Menu/Feature/CatalogSyncService delegation is a
  follow-up.

### Changed

- **BREAKING:** Catalog tables renamed to `larc_menus`, `larc_menu_translations`,
  `larc_features`, `larc_feature_translations` with `uuid`, maintenance columns,
  `parent_key`, `order` (not `sort_order`), translation-only labels, and `menu_id` FK on
  features (manifest/API still expose `menu_key`). Removed `group` from `#[Menu]`.

### Fixed

- `MetadataArtifact::fromArray()` now fail-fast on unsupported or missing
  `schema_version` and on non-array `modules`, matching manifest/ability catalog
  behavior (P4 review gap for cache metadata).

### Changed

- **BREAKING:** Policy ability keys use `Support\KeyIdentifier::fromClass()` (full FQCN
  in dotted snake_case, basename keeps the `Policy` suffix). Example:
  `App\Modules\User\Policies\JournalPolicy` → `app.modules.user.policies.journal_policy`.
  Replaces `Authorization\PolicyKeyDeriver` (removed). `KeyIdentifier::fromPath()` is
  available for path-based identifiers (extension stripped only).
- `Menu` is no longer a repeatable class attribute (`IS_REPEATABLE` removed);
  `Feature` remains repeatable. Empty `Feature.menu` (`''`) is rejected;
  `Feature.key` must be unique across modules and must not equal any `Menu.key`.
  A non-empty `Feature.menu` must not equal any `Feature.key` or the `Menu.key`
  declared on the same controller. Features that sit under a menu are declared on
  a different controller and point `menu` at that menu key.
- Runtime manifest, metadata, and ability catalog paths are locked to
  `bootstrap/cache/laravarc/` (`core-manifest.php`, `core-metadata.php`,
  `authorizer-abilities.php`). Removed config/env keys for manifest/metadata
  store drivers and custom cache paths; providers always use the file driver via
  `CachePath`. Schema snapshot cache remains gated by `schema_cache_enabled` and
  writes to `storage/framework/cache/laravarc-schema/`.

## [1.7.0] - 2026-10-21

### Changed

- `PathValidator` now canonicalises paths without a leading slash (`pathExample` -> `path-example`, `/path/example/` -> `path/example`). Input may still include a leading slash. Feature primary-tab suffix is the empty string; `/` is accepted as an input alias.

[1.7.0]: https://github.com/laravarc/core/releases/tag/v1.7.0

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
