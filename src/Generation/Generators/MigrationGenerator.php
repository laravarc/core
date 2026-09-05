<?php

declare(strict_types=1);

namespace Laravarc\Core\Generation\Generators;

use Laravarc\Core\Generation\GeneratedFile;
use Laravarc\Core\Generation\GenerationContext;
use Laravarc\Core\Generation\GeneratorName;
use Laravarc\Core\Module\ModuleLayout;
use Laravarc\Core\Schema\ColumnSnapshot;

final class MigrationGenerator extends AbstractStubGenerator
{
    use SupportsSelectedGenerator;

    public function name(): string
    {
        return GeneratorName::MIGRATION;
    }

    public function generate(GenerationContext $context): array
    {
        $files = parent::generate($context);
        $files[] = new GeneratedFile(
            relativePath: ModuleLayout::DATABASE.'/'.ModuleLayout::SEEDERS.'/.gitkeep',
            contents: '',
        );

        return $files;
    }

    protected function relativePath(GenerationContext $context): string
    {
        $timestamp = date('Y_m_d_His');

        return 'Database/Migrations/'.$timestamp.'_create_'.$context->tableName.'_table.php';
    }

    /**
     * @return array<string, string>
     */
    protected function variables(GenerationContext $context): array
    {
        return [
            'table' => $context->tableName,
            'columns' => $this->buildColumns($context),
        ];
    }

    private function buildColumns(GenerationContext $context): string
    {
        if ($context->schemaSnapshot === null) {
            return <<<'PHP'
            $table->id();
            $table->timestamps();
PHP;
        }

        $lines = [];

        foreach ($context->schemaSnapshot->columns as $column) {
            $line = $this->columnDefinition($column);
            if ($line !== null) {
                $lines[] = '            '.$line;
            }
        }

        if ($context->schemaSnapshot->timestamps) {
            $lines[] = '            $table->timestamps();';
        }

        if ($context->schemaSnapshot->softDeletes) {
            $lines[] = '            $table->softDeletes();';
        }

        return implode(PHP_EOL, $lines);
    }

    private function columnDefinition(ColumnSnapshot $column): ?string
    {
        if ($column->autoIncrement && $column->isPrimaryKey) {
            return '$table->id();';
        }

        if (in_array($column->name, ['created_at', 'updated_at', 'deleted_at'], true)) {
            return null;
        }

        $definition = match ($column->laravelType) {
            'text' => '$table->text(\''.$column->name.'\')',
            'integer', 'int' => '$table->integer(\''.$column->name.'\')',
            'bigInteger' => '$table->bigInteger(\''.$column->name.'\')',
            'boolean' => '$table->boolean(\''.$column->name.'\')',
            'date' => '$table->date(\''.$column->name.'\')',
            'datetime', 'timestamp' => '$table->dateTime(\''.$column->name.'\')',
            'json' => '$table->json(\''.$column->name.'\')',
            default => '$table->string(\''.$column->name.'\')',
        };

        if ($column->nullable) {
            $definition .= '->nullable()';
        }

        return $definition.';';
    }
}
