<?php

declare(strict_types=1);

namespace Laravarc\Core\Commands\Generation;

use Laravarc\Core\Generation\GeneratorName;

final class GeneratorLabel
{
    public static function for(string $generator): string
    {
        return match ($generator) {
            GeneratorName::MIGRATION => 'Migration',
            GeneratorName::MODEL => 'Model',
            GeneratorName::REPOSITORY => 'Repository',
            GeneratorName::SERVICE => 'Service',
            GeneratorName::CONTROLLER => 'Controller',
            GeneratorName::FORM_REQUEST => 'Form Request',
            GeneratorName::POLICY => 'Policy',
            GeneratorName::RESOURCE => 'Resource',
            GeneratorName::VIEW => 'Views',
            GeneratorName::ROUTE => 'Routes',
            GeneratorName::EVENT => 'Event',
            GeneratorName::LISTENER => 'Listener',
            GeneratorName::SEEDER => 'Seeder',
            GeneratorName::LANG => 'Lang',
            GeneratorName::SERVICE_PROVIDER => 'Service Provider',
            GeneratorName::CORE_EXTENSION => 'Core Extension',
            default => ucfirst(str_replace('-', ' ', str_replace('_', '-', $generator))),
        };
    }
}
