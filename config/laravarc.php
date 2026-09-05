<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Module filesystem path
    |--------------------------------------------------------------------------
    |
    | Absolute path to the root directory where Laravarc feature modules are stored.
    | Each module is a self-contained folder (Controllers, Services, Models, etc.).
    |
    */
    'modules_path' => env('LARAVARC_MODULES_PATH', app_path('Modules')),

    /*
    |--------------------------------------------------------------------------
    | Module PHP namespace
    |--------------------------------------------------------------------------
    |
    | Namespace prefix for generated module classes. Combined with the StudlyCase
    | module path (e.g. Admin/User → App\Modules\Admin\User).
    |
    */
    'module_namespace' => env('LARAVARC_MODULE_NAMESPACE', 'App\\Modules'),

    /*
    |--------------------------------------------------------------------------
    | Locale (code generation)
    |--------------------------------------------------------------------------
    |
    | Default locale for generated lang files when using the "full" preset.
    | Runtime app locale / fallback remain Laravel app.locale / app.fallback_locale.
    |
    */
    'locale' => env('LARAVARC_LOCALE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | I18n (runtime)
    |--------------------------------------------------------------------------
    |
    | Locales accepted by the application (API validation, profile, translation
    | maps). Prefer Laravarc\Core\I18n\Locales — do not hardcode ['id','en'].
    | Empty list falls back to config('app.supported_locales') then app.locale.
    |
    */
    'i18n' => [
        'supported_locales' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env(
                'LARAVARC_SUPPORTED_LOCALES',
                env('APP_SUPPORTED_LOCALES', 'id,en'),
            )),
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Module manifest store
    |--------------------------------------------------------------------------
    |
    | Driver used to persist the module discovery manifest: "file", "json", or "null".
    | The manifest lists every discovered module under modules_path.
    |
    */
    'manifest_store' => env('LARAVARC_MANIFEST_STORE', 'file'),

    /*
    |--------------------------------------------------------------------------
    | Module manifest file paths
    |--------------------------------------------------------------------------
    |
    | Filesystem locations for cached module manifests (PHP and JSON drivers).
    |
    */
    'manifest_file_path' => storage_path('framework/cache/laravarc-modules.manifest.php'),
    'manifest_json_path' => storage_path('framework/cache/laravarc-modules.manifest.json'),

    /*
    |--------------------------------------------------------------------------
    | Schema cache
    |--------------------------------------------------------------------------
    |
    | When enabled, database schema snapshots are cached on disk during generation.
    | Useful in development to avoid repeated introspection; disable in CI if needed.
    |
    */
    'schema_cache_enabled' => env('LARAVARC_SCHEMA_CACHE', false),
    'schema_cache_path' => storage_path('framework/cache/laravarc-schema'),

    /*
    |--------------------------------------------------------------------------
    | Metadata artifact store
    |--------------------------------------------------------------------------
    |
    | Driver for compiled metadata artifacts: "file", "json", "cache", or "null".
    | Metadata is built from controller/service attributes and consumed at boot.
    |
    */
    'metadata_store' => env('LARAVARC_METADATA_STORE', 'file'),

    /*
    |--------------------------------------------------------------------------
    | Metadata artifact file paths
    |--------------------------------------------------------------------------
    |
    | Filesystem locations for compiled metadata (PHP and JSON drivers).
    |
    */
    'metadata_file_path' => storage_path('framework/cache/laravarc-metadata.php'),
    'metadata_json_path' => storage_path('framework/cache/laravarc-metadata.json'),

    /*
    |--------------------------------------------------------------------------
    | Metadata cache key
    |--------------------------------------------------------------------------
    |
    | Cache store key when metadata_store is set to "cache".
    |
    */
    'metadata_cache_key' => env('LARAVARC_METADATA_CACHE_KEY', 'laravarc.metadata'),

    /*
    |--------------------------------------------------------------------------
    | Metadata HTTP endpoint
    |--------------------------------------------------------------------------
    |
    | Optionally expose compiled metadata over HTTP for frontends or tooling.
    | Set expose_metadata_endpoint to true and configure path/middleware below.
    |
    */
    'expose_metadata_endpoint' => env('LARAVARC_EXPOSE_METADATA_ENDPOINT', false),
    'metadata_endpoint_path' => env('LARAVARC_METADATA_ENDPOINT', '/laravarc/metadata'),
    'metadata_endpoint_middleware' => ['auth'],

    /*
    |--------------------------------------------------------------------------
    | Policy enforcement
    |--------------------------------------------------------------------------
    |
    | When true, controller methods without #[Policy] return unauthorized unless
    | the controller class is marked #[Public].
    |
    */
    'require_policy' => env('LARAVARC_REQUIRE_POLICY', false),

    /*
    |--------------------------------------------------------------------------
    | Generated route middleware
    |--------------------------------------------------------------------------
    |
    | Middleware stack applied to every generated module route file.
    | "laravarc.authorize" reads compiled policy metadata and evaluates Gate.
    |
    */
    'route_middleware' => ['api', 'laravarc.authorize'],

    /*
    |--------------------------------------------------------------------------
    | Module route auto-loading
    |--------------------------------------------------------------------------
    |
    | When true, Arc loads every *Route.php file from discovered modules at boot.
    |
    */
    'load_module_routes' => env('LARAVARC_LOAD_MODULE_ROUTES', true),

    /*
    |--------------------------------------------------------------------------
    | Module ServiceProvider auto-registration
    |--------------------------------------------------------------------------
    |
    | When true, Laravarc registers each module's primary {Basename}ServiceProvider
    | from the cached manifest during DiscoveryServiceProvider::register() — before
    | ExtensionManager is configured. Run `php artisan laravarc:cache refresh` after
    | adding or renaming a module ServiceProvider or it will be missing until refresh.
    |
    */
    'load_module_service_providers' => env('LARAVARC_LOAD_MODULE_SERVICE_PROVIDERS', true),

    /*
    |--------------------------------------------------------------------------
    | Module view auto-loading
    |--------------------------------------------------------------------------
    |
    | When true, Arc registers each module's Views/ folder as a Blade namespace
    | equal to the module key (e.g. admin.user → view('admin.user::index')).
    |
    */
    'load_module_views' => env('LARAVARC_LOAD_MODULE_VIEWS', true),

    /*
    |--------------------------------------------------------------------------
    | Module / Shared translation auto-loading
    |--------------------------------------------------------------------------
    |
    | Modules/{Path}/Lang/{locale} → __('module.key::file.line')
    | Shared/{Path}/Langs/{locale} → __('shared.module.key::file.line')
    |
    | Module-private strings stay under Modules/.../Lang/.
    | Cross-module strings belong under Shared/.../Langs/.
    |
    */
    'load_module_translations' => env('LARAVARC_LOAD_MODULE_TRANSLATIONS', true),
    'load_shared_translations' => env('LARAVARC_LOAD_SHARED_TRANSLATIONS', true),

    /*
    |--------------------------------------------------------------------------
    | Module migration auto-loading
    |--------------------------------------------------------------------------
    |
    | When true, Arc registers each module's Database/Migrations path with
    | Laravel's migrator (same effect as loadMigrationsFrom in AppServiceProvider).
    |
    */
    'load_module_migrations' => env('LARAVARC_LOAD_MODULE_MIGRATIONS', true),

    /*
    |--------------------------------------------------------------------------
    | Presentation stacks
    |--------------------------------------------------------------------------
    |
    | Registered presentation stacks (API JsonResource, Blade views, etc.).
    | default_stack selects which stack generators use when --stack is omitted.
    |
    */
    'stacks' => [
        \Laravarc\Core\Presentation\ApiStack::class,
        \Laravarc\Core\Presentation\BladeStack::class,
    ],
    'default_stack' => env('LARAVARC_DEFAULT_STACK', 'api'),

    /*
    |--------------------------------------------------------------------------
    | Default generation preset
    |--------------------------------------------------------------------------
    |
    | Preset used by laravarc:module make when --preset is not provided.
    | Built-in presets: crud, crud+metadata, crud+resource, crud+seed, crud+events, full.
    |
    */
    'default_preset' => env('LARAVARC_DEFAULT_PRESET', 'crud'),

    /*
    |--------------------------------------------------------------------------
    | Shared contracts path
    |--------------------------------------------------------------------------
    |
    | Absolute path to the shared directory for cross-module contracts and events.
    | Layout: {shared_path}/{ModulePath}/Contracts|Events/.
    | Accepts an absolute path or a path relative to the Laravel app directory.
    |
    */
    'shared_path' => env('LARAVARC_SHARED_PATH', app_path('Shared')),

    /*
    |--------------------------------------------------------------------------
    | Custom generation presets
    |--------------------------------------------------------------------------
    |
    | Additional presets registered by bridge packages or the application.
    | Format: 'preset-name' => ['generator', 'names', ...]
    |
    */
    'presets' => [],

    /*
    |--------------------------------------------------------------------------
    | Generator stubs
    |--------------------------------------------------------------------------
    |
    | Override paths for code generation templates.
    | Priority: override_path → published_path → built-in package stubs.
    |
    */
    'stubs' => [
        'override_path' => env('LARAVARC_STUBS_OVERRIDE_PATH'),
        'published_path' => resource_path('stubs/laravarc'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Extension bridges
    |--------------------------------------------------------------------------
    |
    | Optional packages that integrate with Core via CoreExtension.
    | Default is empty — Core runs fully without any of them.
    |
    | Packages in the Laravarc ecosystem are independent: they can be used
    | outside Core. Listing a class here only activates Core integration
    | (hooks, presets, generators). The class must exist and implement
    | Laravarc\Core\Contracts\CoreExtension.
    |
    | Examples (uncomment after: composer require laravarc/{package}):
    |
    */
    'extensions' => [
        // \Laravarc\Eventer\EventerExtension::class,
        // \Laravarc\Core\Authorizer\AuthorizerCoreExtension::class,
        // \Laravarc\Core\Surfacer\SurfacerCoreExtension::class,
        // \Laravarc\Datatable\Datatable::class,
        // \Laravarc\Exchange\Exchange::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Convention resolver bindings
    |--------------------------------------------------------------------------
    |
    | Swap Arc convention resolvers without changing generator code.
    | Each value must implement the corresponding contract interface.
    |
    */
    'convention' => [
        'layer_resolver' => \Laravarc\Core\Convention\DefaultLayerResolver::class,
        'module_key_resolver' => \Laravarc\Core\Convention\DefaultModuleKeyResolver::class,
        'request_resolver' => \Laravarc\Core\Convention\DefaultRequestResolver::class,
    ],
];
