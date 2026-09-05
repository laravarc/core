<?php

declare(strict_types=1);

namespace Laravarc\Core\Commands\Support;

use Illuminate\Filesystem\Filesystem;
use Laravarc\Core\Commands\Services\ModuleGenerationOptions;
use Laravarc\Core\Module\ModuleIdentity;
use Laravarc\Core\Support\ModuleMetaDirectory;

final class PendingGenerationStore
{
    private const STORE_FILE = 'generation.json';

    public function __construct(
        private readonly Filesystem $filesystem,
    ) {}

    public function store(ModuleIdentity $identity, ModuleGenerationOptions $options): void
    {
        $path = $this->storePath($identity);

        $this->filesystem->makeDirectory(dirname($path), 0755, true, true);
        $this->filesystem->put($path, json_encode($this->serialize($options), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    public function load(ModuleIdentity $identity): ?ModuleGenerationOptions
    {
        $path = $this->storePath($identity);

        if (! is_file($path)) {
            return null;
        }

        /** @var array<string, mixed> $data */
        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return $this->deserialize($data);
    }

    public function clear(ModuleIdentity $identity): void
    {
        $path = $this->storePath($identity);

        if (is_file($path)) {
            $this->filesystem->delete($path);
        }
    }

    public function mergeWithStored(ModuleIdentity $identity, ModuleGenerationOptions $cli): ModuleGenerationOptions
    {
        $stored = $this->load($identity);

        if ($stored === null) {
            return $cli;
        }

        return new ModuleGenerationOptions(
            preset: $stored->preset,
            stack: $stored->stack,
            tableOverride: $stored->tableOverride,
            only: $stored->only,
            except: $stored->except,
            refresh: false,
            force: $cli->force,
            dryRun: $cli->dryRun,
            locale: $stored->locale,
            metadata: $stored->metadata,
            withContractAttributes: $stored->withContractAttributes,
            withExtension: $stored->withExtension,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(ModuleGenerationOptions $options): array
    {
        return [
            'preset' => $options->preset,
            'stack' => $options->stack,
            'table' => $options->tableOverride,
            'only' => $options->only,
            'except' => $options->except,
            'locale' => $options->locale,
            'metadata' => $options->metadata,
            'with_contract_attributes' => $options->withContractAttributes,
            'with_extension' => $options->withExtension,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function deserialize(array $data): ModuleGenerationOptions
    {
        return new ModuleGenerationOptions(
            preset: (string) ($data['preset'] ?? config('laravarc.default_preset', 'crud')),
            stack: (string) ($data['stack'] ?? config('laravarc.default_stack', 'api')),
            tableOverride: isset($data['table']) && is_string($data['table']) ? $data['table'] : null,
            only: is_array($data['only'] ?? null) ? array_values($data['only']) : [],
            except: is_array($data['except'] ?? null) ? array_values($data['except']) : [],
            refresh: false,
            force: false,
            dryRun: false,
            locale: isset($data['locale']) && is_string($data['locale']) ? $data['locale'] : null,
            metadata: $this->deserializeMetadata($data),
            withContractAttributes: (bool) ($data['with_contract_attributes'] ?? false),
            withExtension: (bool) ($data['with_extension'] ?? false),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function deserializeMetadata(array $data): mixed
    {
        if (array_key_exists('metadata', $data)) {
            return $data['metadata'];
        }

        if ((bool) ($data['with_metadata_public_only'] ?? false)) {
            return 'public';
        }

        if ((bool) ($data['with_metadata_public'] ?? false)) {
            return 'public,default';
        }

        if ((bool) ($data['with_metadata'] ?? false)) {
            return 'default';
        }

        return null;
    }

    private function storePath(ModuleIdentity $identity): string
    {
        return $identity->rootPath.'/'.ModuleMetaDirectory::NAME.'/'.self::STORE_FILE;
    }
}
