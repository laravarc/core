<?php

declare(strict_types=1);

use Laravarc\Core\Commands\Support\MetadataOptionParser;
use Laravarc\Core\Generation\Metadata\MetadataAttribute;
use Laravarc\Core\Generation\Metadata\MetadataSelection;

describe('MetadataOptionParser', function () {
    it('returns empty selection when metadata option is absent', function () {
        expect((new MetadataOptionParser)->parse(null))->toEqual(MetadataSelection::empty());
    });

    it('expands bare metadata flag to default preset', function () {
        $selection = (new MetadataOptionParser)->parse(true);

        expect($selection->has(MetadataAttribute::Menu))->toBeTrue()
            ->and($selection->has(MetadataAttribute::Feature))->toBeTrue()
            ->and($selection->has(MetadataAttribute::Policy))->toBeTrue()
            ->and($selection->has(MetadataAttribute::Public))->toBeFalse();
    });

    it('expands default preset from explicit value', function () {
        $selection = (new MetadataOptionParser)->parse('default');

        expect($selection->has(MetadataAttribute::Menu))->toBeTrue()
            ->and($selection->has(MetadataAttribute::Feature))->toBeTrue()
            ->and($selection->has(MetadataAttribute::Policy))->toBeTrue();
    });

    it('merges public preset with default attributes', function () {
        $selection = (new MetadataOptionParser)->parse('public,default');

        expect($selection->has(MetadataAttribute::Public))->toBeTrue()
            ->and($selection->has(MetadataAttribute::Menu))->toBeTrue()
            ->and($selection->has(MetadataAttribute::Feature))->toBeTrue()
            ->and($selection->has(MetadataAttribute::Policy))->toBeTrue();
    });

    it('supports single attribute selection', function () {
        $selection = (new MetadataOptionParser)->parse('menu');

        expect($selection->has(MetadataAttribute::Menu))->toBeTrue()
            ->and($selection->has(MetadataAttribute::Feature))->toBeFalse()
            ->and($selection->has(MetadataAttribute::Policy))->toBeFalse();
    });

    it('supports multiple attribute selection without duplicates', function () {
        $selection = (new MetadataOptionParser)->parse('Menu,POLICY,menu');

        expect($selection->attributes)->toHaveCount(2)
            ->and($selection->has(MetadataAttribute::Menu))->toBeTrue()
            ->and($selection->has(MetadataAttribute::Policy))->toBeTrue();
    });

    it('rejects unknown metadata tokens', function () {
        expect(fn () => (new MetadataOptionParser)->parse('menu,unknown'))
            ->toThrow(InvalidArgumentException::class, 'Unknown metadata token [unknown]');
    });
});

describe('MetadataSelection backward compatibility mapping', function () {
    it('maps legacy public-only flag value', function () {
        expect((new MetadataOptionParser)->parse('public')->has(MetadataAttribute::Public))->toBeTrue();
    });
});
