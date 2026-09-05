<?php

declare(strict_types=1);

use Laravarc\Core\Module\ModuleIdentity;
use Laravarc\Core\Schema\CachingSchemaReader;
use Laravarc\Core\Schema\ColumnTypeMapper;
use Laravarc\Core\Schema\DatabaseSchemaReader;
use Laravarc\Core\Schema\Exceptions\MissingPrimaryKeyException;
use Laravarc\Core\Schema\SchemaService;
use Laravarc\Core\Schema\SchemaSnapshotCache;
use Laravarc\Core\Schema\TableNameResolver;
use Laravarc\Core\Tests\Fixtures\FakeSchemaIntrospector;
use Laravarc\Core\Tests\Fixtures\FakeSchemaIntrospectorFactory;

function postsSchemaFixtures(): FakeSchemaIntrospector
{
    return new FakeSchemaIntrospector(
        columns: [
            'posts' => [
                [
                    'name' => 'id',
                    'type_name' => 'integer',
                    'type' => 'integer',
                    'nullable' => false,
                    'default' => null,
                    'auto_increment' => true,
                ],
                [
                    'name' => 'title',
                    'type_name' => 'varchar',
                    'type' => 'varchar(255)',
                    'nullable' => false,
                    'default' => null,
                    'auto_increment' => false,
                ],
                [
                    'name' => 'author_id',
                    'type_name' => 'integer',
                    'type' => 'integer',
                    'nullable' => false,
                    'default' => null,
                    'auto_increment' => false,
                ],
                [
                    'name' => 'created_at',
                    'type_name' => 'datetime',
                    'type' => 'datetime',
                    'nullable' => true,
                    'default' => null,
                    'auto_increment' => false,
                ],
                [
                    'name' => 'updated_at',
                    'type_name' => 'datetime',
                    'type' => 'datetime',
                    'nullable' => true,
                    'default' => null,
                    'auto_increment' => false,
                ],
                [
                    'name' => 'deleted_at',
                    'type_name' => 'datetime',
                    'type' => 'datetime',
                    'nullable' => true,
                    'default' => null,
                    'auto_increment' => false,
                ],
            ],
            'no_pk' => [
                [
                    'name' => 'title',
                    'type_name' => 'varchar',
                    'type' => 'varchar(255)',
                    'nullable' => false,
                    'default' => null,
                    'auto_increment' => false,
                ],
            ],
            'composite_pk' => [
                [
                    'name' => 'tenant_id',
                    'type_name' => 'integer',
                    'type' => 'integer',
                    'nullable' => false,
                    'default' => null,
                    'auto_increment' => false,
                ],
                [
                    'name' => 'user_id',
                    'type_name' => 'integer',
                    'type' => 'integer',
                    'nullable' => false,
                    'default' => null,
                    'auto_increment' => false,
                ],
            ],
            'users' => [
                [
                    'name' => 'id',
                    'type_name' => 'integer',
                    'type' => 'integer',
                    'nullable' => false,
                    'default' => null,
                    'auto_increment' => true,
                ],
                [
                    'name' => 'email',
                    'type_name' => 'varchar',
                    'type' => 'varchar(255)',
                    'nullable' => false,
                    'default' => null,
                    'auto_increment' => false,
                ],
                [
                    'name' => 'created_at',
                    'type_name' => 'datetime',
                    'type' => 'datetime',
                    'nullable' => true,
                    'default' => null,
                    'auto_increment' => false,
                ],
                [
                    'name' => 'updated_at',
                    'type_name' => 'datetime',
                    'type' => 'datetime',
                    'nullable' => true,
                    'default' => null,
                    'auto_increment' => false,
                ],
            ],
            'app_users' => [
                [
                    'name' => 'id',
                    'type_name' => 'integer',
                    'type' => 'integer',
                    'nullable' => false,
                    'default' => null,
                    'auto_increment' => true,
                ],
                [
                    'name' => 'name',
                    'type_name' => 'varchar',
                    'type' => 'varchar(255)',
                    'nullable' => false,
                    'default' => null,
                    'auto_increment' => false,
                ],
            ],
            'cached_users' => [
                [
                    'name' => 'id',
                    'type_name' => 'integer',
                    'type' => 'integer',
                    'nullable' => false,
                    'default' => null,
                    'auto_increment' => true,
                ],
                [
                    'name' => 'name',
                    'type_name' => 'varchar',
                    'type' => 'varchar(255)',
                    'nullable' => false,
                    'default' => null,
                    'auto_increment' => false,
                ],
            ],
        ],
        indexes: [
            'posts' => [
                ['name' => 'primary', 'columns' => ['id'], 'type' => 'btree', 'unique' => true, 'primary' => true],
                ['name' => 'posts_title_unique', 'columns' => ['title'], 'type' => 'btree', 'unique' => true, 'primary' => false],
            ],
            'no_pk' => [],
            'composite_pk' => [
                ['name' => 'primary', 'columns' => ['tenant_id', 'user_id'], 'type' => 'btree', 'unique' => true, 'primary' => true],
            ],
            'users' => [
                ['name' => 'primary', 'columns' => ['id'], 'type' => 'btree', 'unique' => true, 'primary' => true],
                ['name' => 'users_email_unique', 'columns' => ['email'], 'type' => 'btree', 'unique' => true, 'primary' => false],
            ],
            'app_users' => [
                ['name' => 'primary', 'columns' => ['id'], 'type' => 'btree', 'unique' => true, 'primary' => true],
            ],
            'cached_users' => [
                ['name' => 'primary', 'columns' => ['id'], 'type' => 'btree', 'unique' => true, 'primary' => true],
            ],
        ],
        foreignKeys: [
            'posts' => [
                [
                    'name' => 'posts_author_id_foreign',
                    'columns' => ['author_id'],
                    'foreign_table' => 'users',
                    'foreign_columns' => ['id'],
                    'on_update' => 'no action',
                    'on_delete' => 'set null',
                ],
            ],
        ],
    );
}

describe('TableNameResolver', function () {
    it('uses module default table name when override is absent', function () {
        $modulesPath = sys_get_temp_dir().'/arc-schema-name-'.uniqid('', true);
        mkdir($modulesPath, 0777, true);

        $identity = ModuleIdentity::fromPath('admin/user', $modulesPath, 'App\\Modules');
        $table = (new TableNameResolver)->resolve($identity);

        expect($table)->toBe('users');

        rmdir($modulesPath);
    });

    it('applies table override when provided', function () {
        $modulesPath = sys_get_temp_dir().'/arc-schema-override-'.uniqid('', true);
        mkdir($modulesPath, 0777, true);

        $identity = ModuleIdentity::fromPath('admin/user', $modulesPath, 'App\\Modules');
        $table = (new TableNameResolver)->resolve($identity, 'app_users');

        expect($table)->toBe('app_users');

        rmdir($modulesPath);
    });
});

describe('DatabaseSchemaReader', function () {
    beforeEach(function () {
        $this->reader = new DatabaseSchemaReader(
            new FakeSchemaIntrospectorFactory(postsSchemaFixtures()),
            new ColumnTypeMapper,
        );
    });

    it('reads schema snapshot for an existing table', function () {
        $snapshot = $this->reader->read('posts');

        expect($snapshot->tableName)->toBe('posts')
            ->and($snapshot->primaryKey)->toBe(['id'])
            ->and($snapshot->timestamps)->toBeTrue()
            ->and($snapshot->softDeletes)->toBeTrue()
            ->and($snapshot->driver)->toBe('sqlite')
            ->and($snapshot->columns)->not->toBeEmpty();

        $title = collect($snapshot->columns)->firstWhere('name', 'title');

        expect($title?->laravelType)->toBe('string')
            ->and($title?->nullable)->toBeFalse()
            ->and($title?->length)->toBe(255);
    });

    it('captures foreign key metadata on columns', function () {
        $snapshot = $this->reader->read('posts');
        $authorId = collect($snapshot->columns)->firstWhere('name', 'author_id');

        expect($authorId?->foreignKey)->not->toBeNull()
            ->and($authorId?->foreignKey?->referencedTable)->toBe('users')
            ->and($authorId?->foreignKey?->referencedColumn)->toBe('id')
            ->and($authorId?->foreignKey?->onDelete)->toBe('set null');
    });

    it('reports composite primary keys for downstream warning', function () {
        $snapshot = $this->reader->read('composite_pk');

        expect($snapshot->primaryKey)->toBe(['tenant_id', 'user_id'])
            ->and($snapshot->hasCompositePrimaryKey())->toBeTrue();
    });

    it('fails when table has no primary key', function () {
        expect(fn () => $this->reader->read('no_pk'))
            ->toThrow(MissingPrimaryKeyException::class, 'primary key');
    });

    it('reports table existence', function () {
        expect($this->reader->tableExists('posts'))->toBeTrue()
            ->and($this->reader->tableExists('missing_table'))->toBeFalse();
    });
});

describe('SchemaService', function () {
    beforeEach(function () {
        $this->modulesPath = sys_get_temp_dir().'/arc-schema-service-'.uniqid('', true);
        mkdir($this->modulesPath, 0777, true);
        $this->identity = ModuleIdentity::fromPath('admin/user', $this->modulesPath, 'App\\Modules');
        $this->service = new SchemaService(
            new DatabaseSchemaReader(
                new FakeSchemaIntrospectorFactory(postsSchemaFixtures()),
                new ColumnTypeMapper,
            ),
            new TableNameResolver,
        );
    });

    afterEach(function () {
        rmdir($this->modulesPath);
    });

    it('reads snapshot using resolved default table name', function () {
        $snapshot = $this->service->readSnapshot($this->identity);

        expect($snapshot->tableName)->toBe('users')
            ->and($snapshot->primaryKey)->toBe(['id']);
    });

    it('reads snapshot using table override', function () {
        $snapshot = $this->service->readSnapshot($this->identity, tableOverride: 'app_users');

        expect($snapshot->tableName)->toBe('app_users');
    });
});

describe('CachingSchemaReader', function () {
    it('serves cached snapshots when debug cache is enabled', function () {
        $cacheDirectory = sys_get_temp_dir().'/arc-schema-cache-'.uniqid('', true);
        $cache = new SchemaSnapshotCache($cacheDirectory);
        $inner = new DatabaseSchemaReader(
            new FakeSchemaIntrospectorFactory(postsSchemaFixtures()),
            new ColumnTypeMapper,
        );

        $connection = Mockery::mock(\Illuminate\Database\Connection::class);
        $connection->shouldReceive('getName')->andReturn('testing');

        $databaseManager = Mockery::mock(\Illuminate\Database\DatabaseManager::class);
        $databaseManager->shouldReceive('connection')->with(null)->andReturn($connection);

        $reader = new CachingSchemaReader($inner, $cache, $databaseManager);

        $first = $reader->read('cached_users');
        $second = $reader->read('cached_users');

        expect($second->toArray())->toBe($first->toArray());

        $cache->clear();
        rmdir($cacheDirectory);
    });
});

describe('SchemaSnapshot serialization', function () {
    it('round-trips through array representation', function () {
        $snapshot = (new DatabaseSchemaReader(
            new FakeSchemaIntrospectorFactory(postsSchemaFixtures()),
            new ColumnTypeMapper,
        ))->read('posts');

        $restored = \Laravarc\Core\Schema\SchemaSnapshot::fromArray($snapshot->toArray());

        expect($restored->toArray())->toBe($snapshot->toArray());
    });
});
