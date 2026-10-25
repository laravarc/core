<?php

declare(strict_types=1);

namespace Laravarc\Core\Discovery;

final readonly class ModuleManifestEntry
{
    /**
     * @param  list<class-string<\Laravarc\Core\Contracts\ModuleServiceProviderContract>>  $providers
     * @param  list<class-string<\Laravarc\Core\Contracts\ModuleMiddlewareContract>>  $middlewares
     * @param  list<array{concrete: string, contract: string}>  $services
     * @param  list<array{event: string, listener: string}>  $listeners
     */
    public function __construct(
        public string $path,
        public string $key,
        public string $namespace,
        public string $rootPath,
        public array $providers = [],
        public array $middlewares = [],
        public array $services = [],
        public array $listeners = [],
    ) {}

    /**
     * @param  list<array{concrete: string, contract: string}>  $services
     * @param  list<array{event: string, listener: string}>  $listeners
     */
    public function withBindings(array $services, array $listeners): self
    {
        return new self(
            path: $this->path,
            key: $this->key,
            namespace: $this->namespace,
            rootPath: $this->rootPath,
            providers: $this->providers,
            middlewares: $this->middlewares,
            services: $services,
            listeners: $listeners,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'key' => $this->key,
            'namespace' => $this->namespace,
            'providers' => $this->providers,
            'middlewares' => $this->middlewares,
            'services' => $this->services,
            'listeners' => $this->listeners,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, string $modulesRoot): self
    {
        $resolvedRoot = realpath($modulesRoot) ?: $modulesRoot;
        $normalizedRoot = rtrim(str_replace('\\', '/', $resolvedRoot), '/');

        return new self(
            path: (string) $data['path'],
            key: (string) $data['key'],
            namespace: (string) $data['namespace'],
            rootPath: self::resolveRootPath($normalizedRoot, (string) $data['path']),
            providers: array_values(is_array($data['providers'] ?? null) ? $data['providers'] : []),
            middlewares: array_values(is_array($data['middlewares'] ?? null) ? $data['middlewares'] : []),
            services: array_values(is_array($data['services'] ?? null) ? $data['services'] : []),
            listeners: array_values(is_array($data['listeners'] ?? null) ? $data['listeners'] : []),
        );
    }

    /**
     * Resolve the on-disk module root, tolerating case differences between the
     * canonical manifest path (StudlyCase) and the filesystem. The direct hit is
     * attempted first so the common case costs no directory scan.
     */
    private static function resolveRootPath(string $modulesRoot, string $path): string
    {
        $current = $modulesRoot;

        foreach (explode('/', trim($path, '/')) as $segment) {
            if ($segment === '') {
                continue;
            }

            $candidate = $current.'/'.$segment;

            if (! is_dir($candidate) && is_dir($current)) {
                foreach (scandir($current) ?: [] as $item) {
                    if ($item === '.' || $item === '..') {
                        continue;
                    }

                    if (strcasecmp($item, $segment) === 0 && is_dir($current.'/'.$item)) {
                        $candidate = $current.'/'.$item;
                        break;
                    }
                }
            }

            $current = $candidate;
        }

        return $current;
    }
}
