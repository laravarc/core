<?php

declare(strict_types=1);

namespace Laravarc\Core\Metadata;

final readonly class MetadataArtifact
{
    /**
     * @param  array<string, array<string, mixed>>  $modules
     */
    public function __construct(
        public array $modules,
        public ?string $compiledAt = null,
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
            'compiled_at' => $this->compiledAt,
            'modules' => $this->modules,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array<string, array<string, mixed>> $modules */
        $modules = is_array($data['modules'] ?? null) ? $data['modules'] : [];

        return new self(
            modules: $modules,
            compiledAt: isset($data['compiled_at']) ? (string) $data['compiled_at'] : null,
        );
    }
}
