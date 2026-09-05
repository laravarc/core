<?php

declare(strict_types=1);

namespace Laravarc\Core\Discovery;

final readonly class ModuleManifestEntry
{
    /**
     * @param  list<class-string<\Laravarc\Core\Contracts\ModuleServiceProviderContract>>  $providers
     */
    public function __construct(
        public string $path,
        public string $key,
        public string $namespace,
        public string $rootPath,
        public string $discoveredAt,
        public array $providers = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'key' => $this->key,
            'namespace' => $this->namespace,
            'rootPath' => $this->rootPath,
            'discoveredAt' => $this->discoveredAt,
            'providers' => $this->providers,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var list<class-string<\Laravarc\Core\Contracts\ModuleServiceProviderContract>> $providers */
        $providers = $data['providers'] ?? [];

        return new self(
            path: (string) $data['path'],
            key: (string) $data['key'],
            namespace: (string) $data['namespace'],
            rootPath: (string) $data['rootPath'],
            discoveredAt: (string) $data['discoveredAt'],
            providers: $providers,
        );
    }
}
