<?php

declare(strict_types=1);

namespace Laravarc\Core\AiRules;

use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use RuntimeException;

final class AiRulesPackageResolver
{
    private ?string $packagePath = null;

    private ?string $version = null;

    private ?AiRulesManifest $manifest = null;

    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly ?string $packageRoot = null,
    ) {}

    public function packagePath(): string
    {
        if ($this->packagePath !== null) {
            return $this->packagePath;
        }

        $path = rtrim($this->packageRoot ?? dirname(__DIR__, 2), '/').'/ai-rules';

        if (! $this->filesystem->isDirectory($path)) {
            throw new RuntimeException(sprintf('Laravarc AI rules directory not found at [%s].', $path));
        }

        return $this->packagePath = $path;
    }

    public function version(): string
    {
        if ($this->version !== null) {
            return $this->version;
        }

        $versionPath = $this->packagePath().'/VERSION';

        if (! $this->filesystem->exists($versionPath)) {
            throw new RuntimeException(sprintf('Laravarc AI rules VERSION file not found at [%s].', $versionPath));
        }

        $version = trim((string) $this->filesystem->get($versionPath));

        if ($version === '') {
            throw new RuntimeException('Laravarc AI rules VERSION file is empty.');
        }

        return $this->version = $version;
    }

    public function manifest(): AiRulesManifest
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }

        $manifestPath = $this->packagePath().'/manifest.json';

        if (! $this->filesystem->exists($manifestPath)) {
            throw new RuntimeException(sprintf('Laravarc AI rules manifest not found at [%s].', $manifestPath));
        }

        try {
            $decoded = json_decode((string) $this->filesystem->get($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new InvalidArgumentException(
                sprintf('Laravarc AI rules manifest at [%s] contains invalid JSON.', $manifestPath),
                previous: $exception,
            );
        }

        if (! is_array($decoded)) {
            throw new InvalidArgumentException(sprintf('Laravarc AI rules manifest at [%s] must decode to an object.', $manifestPath));
        }

        $manifest = AiRulesManifest::fromArray($decoded, $this->packagePath());

        if ($manifest->rulesVersion !== $this->version()) {
            throw new RuntimeException(sprintf(
                'Laravarc AI rules manifest version [%s] does not match VERSION file [%s].',
                $manifest->rulesVersion,
                $this->version(),
            ));
        }

        foreach ($manifest->rules as $rule) {
            if (! $this->filesystem->exists($rule->absolutePath)) {
                throw new RuntimeException(sprintf('Arc AI rule file not found at [%s].', $rule->absolutePath));
            }
        }

        return $this->manifest = $manifest;
    }

    /**
     * @return list<AiRuleEntry>
     */
    public function ruleFiles(): array
    {
        return $this->manifest()->rules;
    }
}
