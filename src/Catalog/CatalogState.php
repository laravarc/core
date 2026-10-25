<?php

declare(strict_types=1);

namespace Laravarc\Core\Catalog;

use Laravarc\Core\Support\CachePath;

final class CatalogState
{
    public function path(): string
    {
        return CachePath::catalogState();
    }

    public function isInstalled(): bool
    {
        return $this->read()['installed'];
    }

    public function revision(): int
    {
        return $this->read()['revision'];
    }

    /**
     * @return array{installed: bool, revision: int, installed_at: string|null}
     */
    public function read(): array
    {
        $path = $this->path();

        if (! is_readable($path)) {
            return [
                'installed' => false,
                'revision' => 0,
                'installed_at' => null,
            ];
        }

        $data = @include $path;

        if (! is_array($data)) {
            return [
                'installed' => false,
                'revision' => 0,
                'installed_at' => null,
            ];
        }

        $catalog = $data['catalog'] ?? null;
        $block = is_array($catalog) ? $catalog : $data;

        return [
            'installed' => (bool) ($block['installed'] ?? false),
            'revision' => (int) ($block['revision'] ?? 0),
            'installed_at' => isset($block['installed_at']) ? (string) $block['installed_at'] : null,
        ];
    }

    public function markInstalled(): void
    {
        $current = $this->read();
        $installedAt = $current['installed_at'];

        if ($installedAt === null) {
            $installedAt = gmdate('c');
        }

        $this->write([
            'installed' => true,
            'revision' => max(0, $current['revision']),
            'installed_at' => $installedAt,
        ]);
    }

    public function markUninstalled(): void
    {
        $path = $this->path();

        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function bumpRevision(): void
    {
        $current = $this->read();

        if (! $current['installed']) {
            return;
        }

        $this->write([
            'installed' => true,
            'revision' => $current['revision'] + 1,
            'installed_at' => $current['installed_at'],
        ]);
    }

    /**
     * @param  array{installed: bool, revision: int, installed_at: string|null}  $payload
     */
    private function write(array $payload): void
    {
        $path = $this->path();
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $export = var_export([
            'catalog' => [
                'installed' => $payload['installed'],
                'revision' => $payload['revision'],
                'installed_at' => $payload['installed_at'],
            ],
        ], true);

        file_put_contents($path, "<?php\n\ndeclare(strict_types=1);\n\nreturn {$export};\n");
    }
}
