<?php

declare(strict_types=1);

namespace Laravarc\Core\Authorization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PolicyTargetResolver
{
    /** @var list<string> */
    private const CLASS_LEVEL_ABILITIES = ['viewAny', 'create'];

    public function resolve(Request $request, string $ability, ?string $modelClass): mixed
    {
        if ($modelClass === null || $modelClass === '') {
            return null;
        }

        if (in_array($ability, self::CLASS_LEVEL_ABILITIES, true)) {
            return $modelClass;
        }

        $instance = $this->resolveModelInstance($request, $modelClass);

        if ($instance === null) {
            throw new NotFoundHttpException;
        }

        return $instance;
    }

    private function resolveModelInstance(Request $request, string $modelClass): ?object
    {
        $route = $request->route();

        if ($route === null) {
            return null;
        }

        foreach ($route->parameters() as $parameter) {
            if ($parameter instanceof Model && is_a($parameter, $modelClass)) {
                return $parameter;
            }
        }

        if (! class_exists($modelClass)) {
            return null;
        }

        $id = $this->resolveRouteIdentifier($request);

        if ($id === null) {
            return null;
        }

        /** @var Model $model */
        $model = app($modelClass);

        return $this->resolveByIdentifier($model, $id);
    }

    private function resolveByIdentifier(Model $model, mixed $id): ?Model
    {
        if (method_exists($model, 'usesUniqueIds') && $model->usesUniqueIds()) {
            $column = $model->uniqueIds()[0] ?? $model->getKeyName();

            /** @var Model|null $resolved */
            $resolved = $model->newQuery()->where($column, $id)->first();

            return $resolved;
        }

        /** @var Model|null $resolved */
        $resolved = $model->newQuery()->find($id);

        return $resolved;
    }

    private function resolveRouteIdentifier(Request $request): mixed
    {
        $route = $request->route();

        if ($route === null) {
            return null;
        }

        foreach ($route->parameters() as $name => $parameter) {
            if (in_array($name, ['ability', 'abilityOverride'], true)) {
                continue;
            }

            if (is_scalar($parameter) || $parameter === null) {
                return $parameter;
            }
        }

        return null;
    }
}
