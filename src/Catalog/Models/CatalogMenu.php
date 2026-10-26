<?php

declare(strict_types=1);

namespace Laravarc\Core\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $key
 * @property string|null $area
 * @property string|null $parent_key
 * @property string $source
 * @property string|null $icon
 * @property string|null $path
 * @property bool $path_locked
 * @property int $order
 * @property bool $order_locked
 * @property string $status
 * @property bool $is_orphaned
 * @property \Illuminate\Support\Carbon|null $orphaned_at
 * @property \Illuminate\Support\Carbon|null $maintenance_starts_at
 * @property int|null $maintenance_notify_before_value
 * @property string|null $maintenance_notify_before_unit
 */
final class CatalogMenu extends Model
{
    protected $table = 'larc_menus';

    protected $fillable = [
        'uuid',
        'key',
        'area',
        'parent_key',
        'source',
        'icon',
        'path',
        'path_locked',
        'order',
        'order_locked',
        'status',
        'is_orphaned',
        'orphaned_at',
        'maintenance_starts_at',
        'maintenance_notify_before_value',
        'maintenance_notify_before_unit',
    ];

    protected $casts = [
        'path_locked' => 'boolean',
        'order_locked' => 'boolean',
        'is_orphaned' => 'boolean',
        'orphaned_at' => 'datetime',
        'maintenance_starts_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $menu): void {
            if ($menu->uuid === null || $menu->uuid === '') {
                $menu->uuid = (string) Str::uuid();
            }
        });
    }

    /** @return HasMany<CatalogMenuTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(CatalogMenuTranslation::class, 'menu_id');
    }

    /** @return HasMany<CatalogFeature, $this> */
    public function features(): HasMany
    {
        return $this->hasMany(CatalogFeature::class, 'menu_id');
    }
}
