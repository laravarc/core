<?php

declare(strict_types=1);

namespace Laravarc\Core\AiRules;

final readonly class AiRuleEntry
{
    public function __construct(
        public string $id,
        public string $file,
        public string $version,
        public string $scope,
        public int $priority,
        public string $checksum,
        public string $absolutePath,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, string $packagePath): self
    {
        $file = (string) ($data['file'] ?? '');

        return new self(
            id: (string) ($data['id'] ?? ''),
            file: $file,
            version: (string) ($data['version'] ?? ''),
            scope: (string) ($data['scope'] ?? ''),
            priority: (int) ($data['priority'] ?? 0),
            checksum: (string) ($data['checksum'] ?? ''),
            absolutePath: rtrim($packagePath, '/').'/'.$file,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'file' => $this->file,
            'version' => $this->version,
            'scope' => $this->scope,
            'priority' => $this->priority,
            'checksum' => $this->checksum,
        ];
    }
}
