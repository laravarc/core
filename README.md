# Laravarc Core

Modular Laravel toolkit for building **feature modules** — convention-driven backend bundles with database-first code generation, compiled metadata, and Gate-based authorization.

Laravarc Core is **backend-only**. It generates controllers, services, repositories, policies, routes, and related PHP artifacts. It does not scaffold frontend views or components.

Works in any Laravel 10–13 application. Companion packages (`laravarc/authorizer`, `laravarc/eventer`, `laravarc/surfacer`) are optional.

## Requirements

- PHP 8.2+
- Laravel 10, 11, 12, or 13
- A database connection (for schema-driven generation after migration)

## Installation

```bash
composer require laravarc/core
php artisan vendor:publish --tag=laravarc-config
```

Laravel auto-discovers `Laravarc\Core\Providers\CoreServiceProvider`.

Artisan commands use the prefix `laravarc:` (alias `larc:`).

### Optional packages

```bash
composer require laravarc/eventer
php artisan laravarc:install eventer
```

Core does not require Eventer. `#[ListenTo]` listener binding works with or without it. When Eventer is installed, generated `crud+events` command-service stubs dispatch via `Eventer::dispatch(...)`; otherwise they use Laravel `event(...)`.

### Configuration

Publishing copies `config/laravarc.php` into your application.

| Setting | Default | Purpose |
|---------|---------|---------|
| `modules_path` | `app/Modules` (`LARAVARC_MODULES_PATH`) | Root directory for feature modules |
| `module_namespace` | `App\Modules` (`LARAVARC_MODULE_NAMESPACE`) | PHP namespace prefix |
| `shared_path` | `app/Shared` (`LARAVARC_SHARED_PATH`) | Shared contracts and public events |
| `default_stack` | `api` | Presentation stack (`api` or `blade`) |
| `default_preset` | `crud` | Generator preset |
| `route_middleware` | `['api', 'laravarc.authorize']` | Middleware on generated routes |
| `load_module_routes` | `true` | Auto-load `*Route.php` files from each module |
| `load_module_service_providers` | `true` | Auto-register primary module service providers |
| `expose_metadata_endpoint` | `false` | Expose `GET /laravarc/metadata` |
| `metadata_store` | `file` | Metadata artifact driver |

### Stubs (optional)

```bash
php artisan vendor:publish --tag=laravarc-stubs
```

Published stubs live in `resources/stubs/laravarc/`. Override order: application override path → published → built-in.

Set `LARAVARC_STUBS_OVERRIDE_PATH` in `.env` for a custom override directory.

### Module autoloading

Add your modules path to Composer PSR-4 in the application's `composer.json`:

```json
{
  "autoload": {
    "psr-4": {
      "App\\Modules\\": "app/Modules/"
    }
  }
}
```

```bash
composer dump-autoload
```

With the default `"App\\": "app/"` mapping, `app/Shared` is already autoloaded. Register extra PSR-4 mappings only when `shared_path` is outside `app/`.

## Module routes

Laravarc Core loads route files from discovered modules at boot. Each module route file lives at `{ModuleRoot}/Routes/{EntityName}Route.php` (for example `Routes/UserRoute.php` for `admin/user`). All `*Route.php` files in `Routes/` are loaded.

Disable automatic loading when you prefer to register routes yourself:

```env
LARAVARC_LOAD_MODULE_ROUTES=false
```

## Policy registration

Laravarc Core registers model-to-policy bindings with Laravel Gate at boot via `CorePolicyRegistrar`, using the compiled metadata artifact. No manual `$policies` map is required for generated modules.

Compile metadata after generation:

```bash
php artisan laravarc:metadata compile
# or
php artisan laravarc:cache refresh
```

If the artifact is missing, policy registration is skipped until metadata is compiled.

## Quick start

This walkthrough creates an `admin/user` module.

### 1. Scaffold (migration only)

When the database table does not exist yet, only the migration is generated:

```bash
php artisan laravarc:module make admin/user
```

Output: `app/Modules/Admin/User/Database/Migrations/*_create_users_table.php`

### 2. Migrate and generate the stack

```bash
php artisan laravarc:migrate --module=admin/user
```

Laravarc Core runs the module migration, reads the live schema, and generates the CRUD stack (model, repository, service, controller, form requests, policy, routes).

Use `--preset=crud+resource` for API `JsonResource` classes, or `--stack=blade` for Blade return templates.

### 3. Metadata (optional)

Emit `Menu`, `Feature`, and `Policy` attributes:

```bash
php artisan laravarc:module make admin/user --refresh --metadata
# equivalent:
php artisan laravarc:module make admin/user --refresh --metadata=default

# Public controller (no per-method Policy required when require_policy is on):
php artisan laravarc:module make admin/user --refresh --metadata=public,default

# Public attribute only:
php artisan laravarc:module make admin/user --refresh --metadata=public

# Selected attributes only:
php artisan laravarc:module make admin/user --refresh --metadata=menu,policy
```

When the table does not exist yet, only the migration is generated and your options (including `--metadata`) are stored. After `laravarc:migrate`, those options are applied automatically.

```bash
php artisan laravarc:metadata compile
```

```env
LARAVARC_EXPOSE_METADATA_ENDPOINT=true
```

Clients can then call `GET /laravarc/metadata` (default middleware: `auth`).

### 4. Refresh the module manifest

Make, remove, and migrate refresh the manifest automatically. To rebuild manually:

```bash
php artisan laravarc:cache refresh
```

**Module service providers:** During manifest refresh, Laravarc scans each module for a primary `{Basename}ServiceProvider.php` under `Providers/` (basename = StudlyCase of the last path segment). Valid providers must implement `ModuleServiceProviderContract` and are stored in manifest `providers[]`. At boot they are registered in sorted module-path order **before** `ExtensionManager` is configured, so `config()->push('laravarc.extensions', ...)` in `register()` is visible.

If you add or rename a module service provider, run `laravarc:cache refresh`. A stale manifest means the provider is skipped at boot.

Only the primary `{Basename}ServiceProvider` is discovered. Register secondary providers from the primary:

```php
public function register(): void
{
    $this->app->register(CatalogEventServiceProvider::class);
    config()->push('laravarc.extensions', CatalogCoreExtension::class);
}
```

```bash
php artisan laravarc:module make admin/platform/foo --with-extension
```

Disable auto-registration with `LARAVARC_LOAD_MODULE_SERVICE_PROVIDERS=false` and register module providers yourself (for example in `bootstrap/providers.php`).

## CLI commands

| Command | Description |
|---------|-------------|
| `laravarc:module make {path}` | Create or regenerate a module |
| `laravarc:module remove {path}` | Remove a module directory (double confirmation; `--force` skips prompts) |
| `laravarc:module migrate {source} {target}` | Relocate a module and update FQCN references (`--dry-run`, `--force`) |
| `laravarc:migrate` | Run module migrations; continue generation when needed |
| `laravarc:seed` | Run module seeders (`--module=` to scope) |
| `laravarc:metadata compile` | Compile metadata artifact (`--module=` to scope) |
| `laravarc:contract sync` | Sync Command/Query service contracts from attributes |
| `laravarc:cache refresh` | Rebuild module manifest and metadata caches |
| `laravarc:cache clear` | Clear manifest and metadata caches |

Common options: `--force`, `--dry-run`, `--preset=`, `--stack=`, `--only=`, `--except=`, `--metadata[=VALUE]`, `--contract`, `--with-extension`, `--refresh`.

## Generation presets

| Preset | Generators |
|--------|------------|
| `crud` (default) | migration, model, repository, service, controller, form-request, policy, route |
| `crud+metadata` | Same as `crud`; emits metadata attributes |
| `crud+resource` | crud + JsonResource |
| `crud+seed` | crud + seeder |
| `crud+events` | crud + event + listener |
| `full` | All generators including lang |

Use `--metadata[=VALUE]` with any preset that includes controller and policy generators. Valid values: attributes (`menu`, `feature`, `policy`, `public`) and presets (`default` = menu + feature + policy). Comma-separated values are merged and deduplicated.

## Module layout

```text
app/Modules/Admin/User/
├── Controllers/
├── FormRequests/
├── Services/
│   ├── Commands/    # {Entity}CommandService (writes)
│   └── Queries/     # {Entity}QueryService (reads)
├── Repositories/
├── Policies/
├── Models/
├── Routes/
├── Database/
│   ├── Migrations/
│   └── Seeders/
├── Events/          (optional)
├── Listeners/       (optional)
├── Resources/       (api stack)
├── Views/           (blade stack — view('{moduleKey}::name'))
└── Lang/            (optional)
```

Directories use StudlyCase (`Admin/User`). The module key stays lowercase dot notation (`admin.user`); the namespace is `App\Modules\Admin\User`.

Generated models include `$casts` for non-string fillable columns and `deleted_at` when soft deletes are present.

Nested paths such as `catalog/product` become `Catalog/Product` on disk, module key `catalog.product`, and default route prefix `catalog/product`.

## Shared folder

`{shared_path}` (default `app/Shared`) holds cross-module artifacts:

| Layout | Purpose |
|--------|---------|
| `{ModulePath}/Contracts/` | Command/Query service interfaces (`--contract`, `laravarc:contract sync`) |
| `{ModulePath}/Events/` | Domain events from `crud+events` |

```text
App\Shared\{ModulePath}\Contracts\{Entity}CommandServiceContract
App\Shared\{ModulePath}\Events\{Entity}DeletedEvent
```

## Authorization

Compiled metadata stores `policy` (default model/policy, abilities, controller requirements), menus, and features per module. When Command/Query contracts exist, metadata also stores a `services` array. When listeners exist, it stores a `listeners` array.

At boot, Laravarc Core registers Gate policy bindings, service-contract bindings, and event listeners (`CoreListenerRegistrar`) from the compiled cache.

### Service contracts

New modules split services into Command (write) and Query (read) classes. Use `--contract` to emit attributes and generate interfaces:

```bash
php artisan laravarc:module make admin/user --contract
php artisan laravarc:metadata compile
```

If you add contract attributes later, run `laravarc:contract sync`.

Interfaces are generated at `{shared_path}/{ModulePath}/Contracts/`. Configure via `LARAVARC_SHARED_PATH` or `shared_path`. Sync skips renamed interfaces and contracts with extra methods not declared on the service. Class types get `use` imports (or `\FQCN` on alias conflict). Existing Shared contract imports and method PHPDoc are preserved.

Legacy modules with `Services/{Entity}Service.php` are kept on `--refresh`.

### Domain events

Preset `crud+events` generates plain data event classes under `{shared_path}/{ModulePath}/Events/`. Event files are the same whether or not `laravarc/eventer` is installed.

At generate time, command-service stubs branch on `class_exists(\Laravarc\Eventer\Facades\Eventer::class)`:

- **Eventer installed:** `Eventer::dispatch(new XxxEvent(...))`
- **Eventer absent:** `event(new XxxEvent(...))`

Core has no runtime event dispatcher or transport abstraction. Transports live in `laravarc/eventer`.

### Event listeners

Listeners use `#[ListenTo({Event}::class)]`. Core compiles bindings into metadata and registers them at boot via Laravel `Event::listen()`.

```php
use App\Shared\Admin\User\Events\UserDeletedEvent;
use Laravarc\Core\Metadata\Attributes\ListenTo;

#[ListenTo(UserDeletedEvent::class)]
final class CleanupOrdersOnUserDeletedListener
{
    public function handle(UserDeletedEvent $event): void
    {
        // use primitive IDs from $event
    }
}
```

```bash
php artisan laravarc:metadata compile
```

`#[Menu]` and `#[Feature]` are class-level attributes on controllers. `#[Policy]` on controller methods defines authorization (class-level `#[Policy]` provides defaults). A single attribute accepts one ability or an array (ANY). Repeatable attributes on the same method require ALL requirements to pass.

When `config('laravarc.require_policy')` is enabled, controller methods without `#[Policy]` are unauthorized unless the controller is marked `#[Public]`. Method-level `#[Policy]` still applies on public controllers.

Generated routes use `config('laravarc.route_middleware')`. By default this includes `laravarc.authorize`, which reads compiled controller requirements and evaluates Gate from the cached bindings.

## Extensions

Optional packages integrate through the `CoreExtension` contract. Register bridge classes in `config/laravarc.php`:

```php
'extensions' => [
    // \Laravarc\Authorizer\... or your own CoreExtension
],
```

Default is empty. Ecosystem packages remain usable without Core. Listing a class here only activates Core integration.

`laravarc/eventer` is configured separately (`config/eventer.php`) and is not registered through `extensions`.

## Testing

```bash
composer test
```

## License

MIT — see [LICENSE](LICENSE).
