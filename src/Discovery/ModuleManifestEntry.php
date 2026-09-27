<?php

declare(strict_types=1);

namespace Laravarc\Core\Discovery;

final readonly class ModuleManifestEntry
{
    /**
     * @param  list<class-string<\Laravarc\Core\Contracts\ModuleServiceProviderContract>>  $providers
     * @param  list<class-string<\Laravarc\Core\Contracts\ModuleMiddlewareContract>>  $middlewares
     */
    public function __construct(
        public string $path,
        public string $key,
        public string $namespace,
        public string $rootPath,
        public string $discoveredAt,
        public array $providers = [],
        public array $middlewares = [],
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
            'middlewares' => $this->middlewares,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var list<class-string<\Laravarc\Core\Contracts\ModuleServiceProviderContract>> $providers */
        $providers = $data['providers'] ?? [];

        /** @var list<class-string<\Laravarc\Core\Contracts\ModuleMiddlewareContract>> $middlewares */
        $middlewares = $data['middlewares'] ?? [];

        return new self(
            path: (string) $data['path'],
            key: (string) $data['key'],
            namespace: (string) $data['namespace'],
            rootPath: (string) $data['rootPath'],
            discoveredAt: (string) $data['discoveredAt'],
            providers: $providers,
            middlewares: $middlewares,
        );
    }
}
