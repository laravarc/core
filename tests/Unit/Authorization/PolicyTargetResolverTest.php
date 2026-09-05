<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravarc\Core\Authorization\PolicyTargetResolver;
use Laravarc\Core\Tests\Fixtures\Models\PolicyTargetDualKeyModel;
use Laravarc\Core\Tests\Fixtures\Models\PolicyTargetIntegerModel;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

describe('PolicyTargetResolver', function () {
    it('resolves integer primary keys via find for scalar route parameters', function () {
        $record = new PolicyTargetIntegerModel(['name' => 'alpha']);
        $record->id = 7;

        $query = Mockery::mock(Builder::class);
        $query->shouldReceive('find')->with('7')->once()->andReturn($record);

        bindModelQuery(PolicyTargetIntegerModel::class, $query, usesUniqueIds: false);

        $request = requestWithRouteParameter('id', '7');

        $resolved = (new PolicyTargetResolver)->resolve($request, 'view', PolicyTargetIntegerModel::class);

        expect($resolved)->toBe($record);
    });

    it('resolves dual-key models via uniqueIds column for scalar uuid route parameters', function () {
        $uuid = (string) Str::uuid7();
        $record = new PolicyTargetDualKeyModel(['uuid' => $uuid, 'name' => 'beta']);
        $record->id = 3;

        $query = Mockery::mock(Builder::class);
        $query->shouldReceive('where')->with('uuid', $uuid)->once()->andReturnSelf();
        $query->shouldReceive('first')->once()->andReturn($record);

        bindModelQuery(PolicyTargetDualKeyModel::class, $query, usesUniqueIds: true, uniqueIds: ['uuid']);

        $request = requestWithRouteParameter('id', $uuid);

        $resolved = (new PolicyTargetResolver)->resolve($request, 'view', PolicyTargetDualKeyModel::class);

        expect($resolved)->toBe($record);
    });

    it('prefers bound model instances over scalar route identifiers', function () {
        $record = new PolicyTargetDualKeyModel([
            'uuid' => (string) Str::uuid7(),
            'name' => 'gamma',
        ]);
        $record->id = 5;

        $request = requestWithRouteParameter('id', '00000000-0000-0000-0000-000000000000', $record);

        $resolved = (new PolicyTargetResolver)->resolve($request, 'view', PolicyTargetDualKeyModel::class);

        expect($resolved)->toBe($record);
    });

    it('throws not found when scalar identifier does not match any record', function () {
        $uuid = (string) Str::uuid7();

        $query = Mockery::mock(Builder::class);
        $query->shouldReceive('where')->with('uuid', $uuid)->once()->andReturnSelf();
        $query->shouldReceive('first')->once()->andReturn(null);

        bindModelQuery(PolicyTargetDualKeyModel::class, $query, usesUniqueIds: true, uniqueIds: ['uuid']);

        $request = requestWithRouteParameter('id', $uuid);

        expect(fn () => (new PolicyTargetResolver)->resolve($request, 'view', PolicyTargetDualKeyModel::class))
            ->toThrow(NotFoundHttpException::class);
    });
});

function bindModelQuery(string $modelClass, Builder $query, bool $usesUniqueIds, array $uniqueIds = []): void
{
    /** @var Model&\Mockery\MockInterface $model */
    $model = Mockery::mock($modelClass)->makePartial();
    $model->shouldReceive('usesUniqueIds')->andReturn($usesUniqueIds);

    if ($usesUniqueIds) {
        $model->shouldReceive('uniqueIds')->andReturn($uniqueIds);
    }

    $model->shouldReceive('newQuery')->andReturn($query);

    app()->instance($modelClass, $model);
}

function requestWithRouteParameter(string $name, mixed $value, ?object $boundModel = null): Request
{
    Route::get('/policy-target/{'.$name.'}', fn () => response()->noContent());

    $path = '/policy-target/'.(string) $value;
    $request = Request::create($path, 'GET');
    $route = Route::getRoutes()->match($request);

    if ($boundModel !== null) {
        $route->setParameter($name, $boundModel);
    }

    $request->setRouteResolver(fn () => $route);

    return $request;
}
