<?php

declare(strict_types=1);

namespace Laravarc\Core\Metadata;

use Laravarc\Core\Metadata\Exceptions\CorruptMetadataArtifactException;

final readonly class MetadataArtifact
{
    public const SCHEMA_VERSION = 1;

    /**
     * @param  array<string, array<string, mixed>>  $modules
     */
    public function __construct(
        public array $modules,
        public ?string $compiledAt = null,
        public string $fingerprint = '',
    ) {}

    public static function empty(): self
    {
        return new self(modules: []);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'compiled_at' => $this->compiledAt,
            'generated_by' => 'laravarc core',
            'fingerprint' => $this->fingerprint,
            'modules' => $this->modules,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        if ((int) ($data['schema_version'] ?? 0) !== self::SCHEMA_VERSION) {
            throw new CorruptMetadataArtifactException(sprintf(
                'Unsupported metadata artifact schema_version [%s]; expected [%d]. Run laravarc:cache build.',
                (string) ($data['schema_version'] ?? 'missing'),
                self::SCHEMA_VERSION,
            ));
        }

        $modules = $data['modules'] ?? [];

        if (! is_array($modules)) {
            throw new CorruptMetadataArtifactException('Metadata artifact "modules" must be an array.');
        }

        /** @var array<string, array<string, mixed>> $modules */

        return new self(
            modules: $modules,
            compiledAt: isset($data['compiled_at']) ? (string) $data['compiled_at'] : null,
            fingerprint: (string) ($data['fingerprint'] ?? ''),
        );
    }
}
