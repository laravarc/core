<?php

declare(strict_types=1);

namespace Laravarc\Core\Generation;

use Laravarc\Core\Generation\Exceptions\UnknownGeneratorException;

final class GeneratorRegistry
{
    public function __construct(
        private readonly ModulePresetRegistry $presets,
    ) {}

    /**
     * @param  list<string>  $only
     * @param  list<string>  $except
     */
    public function resolve(
        string $preset,
        string $presentationStack,
        bool $migrationOnly,
        bool $refresh,
        bool $tableExists,
        ?string $selectedLocale,
        array $only,
        array $except,
        bool $withExtension = false,
    ): GeneratorResolution {
        if ($migrationOnly) {
            $generators = [
                GeneratorName::MIGRATION,
                GeneratorName::SERVICE_PROVIDER,
            ];

            if ($withExtension) {
                $generators[] = GeneratorName::CORE_EXTENSION;
            }

            return new GeneratorResolution($generators);
        }

        $warnings = [];

        if ($only !== []) {
            $this->assertKnownGenerators($only);

            if ($except !== []) {
                $warnings[] = 'The --except option was ignored because --only takes precedence.';
            }

            $active = $only;
        } else {
            $active = $this->presets->generatorsFor($preset);

            if ($except !== []) {
                $this->assertKnownGenerators($except);
                $active = array_values(array_filter(
                    $active,
                    static fn (string $name): bool => ! in_array($name, $except, true),
                ));
            }
        }

        $active = $this->applyStackRules($active, $presentationStack);
        $active = $this->applyLocaleRules($active, $selectedLocale);

        if ($withExtension) {
            $active[] = GeneratorName::CORE_EXTENSION;
        }

        if ($refresh) {
            $active = array_values(array_filter(
                $active,
                static fn (string $name): bool => $name !== GeneratorName::MIGRATION,
            ));
        } elseif ($tableExists && $only === []) {
            $active = array_values(array_filter(
                $active,
                static fn (string $name): bool => $name !== GeneratorName::MIGRATION,
            ));
        }

        return new GeneratorResolution(array_values(array_unique($active)), $warnings);
    }

    /**
     * @param  list<string>  $names
     */
    public function assertKnownGenerators(array $names): void
    {
        foreach ($names as $name) {
            if (! in_array($name, GeneratorName::all(), true)) {
                throw new UnknownGeneratorException(sprintf(
                    'Unknown generator [%s]. Valid generators: %s.',
                    $name,
                    implode(', ', GeneratorName::all()),
                ));
            }
        }
    }

    /**
     * @param  list<string>  $generators
     * @return list<string>
     */
    private function applyStackRules(array $generators, string $presentationStack): array
    {
        if ($presentationStack === 'blade') {
            return array_values(array_filter(
                $generators,
                static fn (string $name): bool => $name !== GeneratorName::RESOURCE,
            ));
        }

        return array_values(array_filter(
            $generators,
            static fn (string $name): bool => $name !== GeneratorName::VIEW,
        ));
    }

    /**
     * @param  list<string>  $generators
     * @return list<string>
     */
    private function applyLocaleRules(array $generators, ?string $selectedLocale): array
    {
        if ($selectedLocale !== null && $selectedLocale !== '') {
            return $generators;
        }

        return array_values(array_filter(
            $generators,
            static fn (string $name): bool => $name !== GeneratorName::LANG,
        ));
    }
}
