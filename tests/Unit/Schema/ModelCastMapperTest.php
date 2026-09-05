<?php

declare(strict_types=1);

use Laravarc\Core\Schema\ColumnSnapshot;
use Laravarc\Core\Schema\ModelCastMapper;

describe('ModelCastMapper', function () {
    it('maps non-string column types to cast expressions', function () {
        $mapper = new ModelCastMapper;

        expect($mapper->castForColumn(new ColumnSnapshot(
            name: 'count',
            databaseType: 'int',
            laravelType: 'integer',
            nullable: false,
            default: null,
            autoIncrement: false,
            unsigned: false,
            length: null,
            precision: null,
            scale: null,
            isPrimaryKey: false,
            isUnique: false,
            isIndexed: false,
            foreignKey: null,
        )))->toBe('integer')
            ->and($mapper->castForColumn(new ColumnSnapshot(
                name: 'is_active',
                databaseType: 'boolean',
                laravelType: 'boolean',
                nullable: false,
                default: null,
                autoIncrement: false,
                unsigned: false,
                length: null,
                precision: null,
                scale: null,
                isPrimaryKey: false,
                isUnique: false,
                isIndexed: false,
                foreignKey: null,
            )))->toBe('boolean')
            ->and($mapper->castForColumn(new ColumnSnapshot(
                name: 'amount',
                databaseType: 'decimal',
                laravelType: 'decimal',
                nullable: false,
                default: null,
                autoIncrement: false,
                unsigned: false,
                length: null,
                precision: 10,
                scale: 2,
                isPrimaryKey: false,
                isUnique: false,
                isIndexed: false,
                foreignKey: null,
            )))->toBe('decimal:10:2')
            ->and($mapper->castForColumn(new ColumnSnapshot(
                name: 'meta',
                databaseType: 'json',
                laravelType: 'array',
                nullable: true,
                default: null,
                autoIncrement: false,
                unsigned: false,
                length: null,
                precision: null,
                scale: null,
                isPrimaryKey: false,
                isUnique: false,
                isIndexed: false,
                foreignKey: null,
            )))->toBe('array');
    });

    it('returns null for plain string columns', function () {
        $mapper = new ModelCastMapper;

        expect($mapper->castForColumn(new ColumnSnapshot(
            name: 'email',
            databaseType: 'varchar',
            laravelType: 'string',
            nullable: false,
            default: null,
            autoIncrement: false,
            unsigned: false,
            length: 255,
            precision: null,
            scale: null,
            isPrimaryKey: false,
            isUnique: false,
            isIndexed: false,
            foreignKey: null,
        )))->toBeNull();
    });
});
