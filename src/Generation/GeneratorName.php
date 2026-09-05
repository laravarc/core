<?php

declare(strict_types=1);

namespace Laravarc\Core\Generation;

final class GeneratorName
{
    public const MIGRATION = 'migration';

    public const MODEL = 'model';

    public const REPOSITORY = 'repository';

    public const SERVICE = 'service';

    public const CONTROLLER = 'controller';

    public const FORM_REQUEST = 'form-request';

    public const POLICY = 'policy';

    public const RESOURCE = 'resource';

    public const VIEW = 'view';

    public const ROUTE = 'route';

    public const EVENT = 'event';

    public const LISTENER = 'listener';

    public const SEEDER = 'seeder';

    public const LANG = 'lang';

    public const SERVICE_PROVIDER = 'service_provider';

    public const CORE_EXTENSION = 'core_extension';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::MIGRATION,
            self::MODEL,
            self::REPOSITORY,
            self::SERVICE,
            self::CONTROLLER,
            self::FORM_REQUEST,
            self::POLICY,
            self::RESOURCE,
            self::VIEW,
            self::ROUTE,
            self::EVENT,
            self::LISTENER,
            self::SEEDER,
            self::LANG,
            self::SERVICE_PROVIDER,
            self::CORE_EXTENSION,
        ];
    }
}
