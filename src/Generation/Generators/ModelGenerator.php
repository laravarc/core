<?php

declare(strict_types=1);

namespace Laravarc\Core\Generation\Generators;

use Laravarc\Core\Generation\GenerationContext;
use Laravarc\Core\Generation\GeneratorName;
use Laravarc\Core\Schema\ColumnSnapshot;
use Laravarc\Core\Schema\ModelCastMapper;

final class ModelGenerator extends AbstractStubGenerator
{
    use SupportsSelectedGenerator;

    public function name(): string
    {
        return GeneratorName::MODEL;
    }

    public function supports(GenerationContext $context): bool
    {
        return $this->isSelected($context) && $context->schemaSnapshot !== null;
    }

    protected function relativePath(GenerationContext $context): string
    {
        return $context->classFor('model')['relativePath'];
    }

    /**
     * @return array<string, string>
     */
    protected function variables(GenerationContext $context): array
    {
        $model = $context->classFor('model');

        return [
            'namespace' => $context->moduleNamespace.'\\Models',
            'class' => $model['shortName'],
            'table' => $context->tableName,
            'fillable' => $this->buildFillable($context),
            'casts' => $this->buildCasts($context),
        ];
    }

    private function buildFillable(GenerationContext $context): string
    {
        $columns = array_filter(
            $context->schemaSnapshot->columns,
            static fn (ColumnSnapshot $column): bool => ! $column->autoIncrement
                && ! in_array($column->name, ['created_at', 'updated_at', 'deleted_at'], true),
        );

        if ($columns === []) {
            return '';
        }

        return implode(PHP_EOL, array_map(
            static fn (ColumnSnapshot $column): string => "        '".$column->name."',",
            $columns,
        ));
    }

    private function buildCasts(GenerationContext $context): string
    {
        if ($context->schemaSnapshot === null) {
            return '';
        }

        $mapper = new ModelCastMapper;
        $lines = [];

        foreach ($context->schemaSnapshot->columns as $column) {
            if ($column->autoIncrement) {
                continue;
            }

            if (in_array($column->name, ['created_at', 'updated_at'], true)) {
                continue;
            }

            $isFillableColumn = ! in_array($column->name, ['created_at', 'updated_at', 'deleted_at'], true);
            $isDeletedAtColumn = $column->name === 'deleted_at';

            if (! $isFillableColumn && ! $isDeletedAtColumn) {
                continue;
            }

            $cast = $mapper->castForColumn($column);

            if ($cast === null) {
                continue;
            }

            $lines[] = "        '".$column->name."' => '".$cast."',";
        }

        return implode(PHP_EOL, $lines);
    }
}
