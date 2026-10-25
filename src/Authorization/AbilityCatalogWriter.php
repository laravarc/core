<?php

declare(strict_types=1);

namespace Laravarc\Core\Authorization;

use DateTimeImmutable;
use Laravarc\Core\Metadata\Exceptions\MetadataCompileException;

final class AbilityCatalogWriter
{
    public function __construct(
        private readonly string $path,
    ) {}

    /**
     * @param  array<string, array<string, mixed>>  $policies
     * @param  array<string, array<string, list<list<string>>>>  $gates
     */
    public function write(array $policies, array $gates): void
    {
        ksort($policies);

        $payload = [
            'schema_version' => AbilityCatalogReader::SCHEMA_VERSION,
            'compiled_at' => (new DateTimeImmutable)->format(DATE_ATOM),
            'generated_by' => 'laravarc core',
            'policies' => $policies,
            'gates' => $gates,
        ];

        $directory = dirname($this->path);

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new MetadataCompileException(sprintf('Unable to create ability cache directory [%s].', $directory));
        }

        $temporary = $this->path.'.'.uniqid('arc-ability-', true).'.tmp';
        $contents = "<?php\n\ndeclare(strict_types=1);\n\nreturn ".var_export($payload, true).";\n";

        if (file_put_contents($temporary, $contents) === false) {
            throw new MetadataCompileException(sprintf('Unable to write ability cache [%s].', $temporary));
        }

        if (! rename($temporary, $this->path)) {
            @unlink($temporary);

            throw new MetadataCompileException(sprintf('Unable to publish ability cache [%s].', $this->path));
        }
    }
}
