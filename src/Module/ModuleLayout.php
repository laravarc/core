<?php

declare(strict_types=1);

namespace Laravarc\Core\Module;

final class ModuleLayout
{
    public const CONTROLLERS = 'Controllers';

    public const FORM_REQUESTS = 'FormRequests';

    public const SERVICES = 'Services';

    public const REPOSITORIES = 'Repositories';

    public const POLICIES = 'Policies';

    public const MODELS = 'Models';

    public const VIEWS = 'Views';

    public const RESOURCES = 'Resources';

    public const EVENTS = 'Events';

    public const LISTENERS = 'Listeners';

    public const DATABASE = 'Database';

    public const MIGRATIONS = 'Migrations';

    public const SEEDERS = 'Seeders';

    public const LANG = 'Lang';

    public const ROUTES = 'Routes';

    /**
     * @return list<string>
     */
    public static function mandatoryFolders(): array
    {
        return [
            self::CONTROLLERS,
            self::FORM_REQUESTS,
            self::SERVICES,
            self::REPOSITORIES,
            self::POLICIES,
            self::MODELS,
            self::DATABASE.'/'.self::MIGRATIONS,
            self::ROUTES,
        ];
    }

    /**
     * @return list<string>
     */
    public static function presetOptionalFolders(): array
    {
        return [
            self::EVENTS,
            self::LISTENERS,
            self::DATABASE.'/'.self::SEEDERS,
        ];
    }

    /**
     * @return list<string>
     */
    public static function stackOptionalFolders(): array
    {
        return [
            self::RESOURCES,
            self::VIEWS,
        ];
    }

    /**
     * Relative paths whose presence qualifies a directory as a discoverable module.
     *
     * @return list<string>
     */
    public static function discoverySignalPaths(): array
    {
        return self::mandatoryFolders();
    }

    /**
     * Path segments that cannot be used in a module path (layer folder names).
     *
     * @return list<string>
     */
    public static function reservedPathSegments(): array
    {
        return [
            self::CONTROLLERS,
            self::FORM_REQUESTS,
            self::SERVICES,
            self::REPOSITORIES,
            self::POLICIES,
            self::MODELS,
            self::RESOURCES,
            self::VIEWS,
            self::EVENTS,
            self::LISTENERS,
            self::DATABASE,
            self::MIGRATIONS,
            self::SEEDERS,
            self::LANG,
            self::ROUTES,
        ];
    }
}
