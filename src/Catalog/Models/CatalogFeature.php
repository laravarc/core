<?php

declare(strict_types=1);

namespace Laravarc\Core\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $key
 * @property int|null $menu_id
 * @property string $source
 * @property string|null $path
 * @property bool $path_locked
 * @property int $order
 * @property string|null $placement
 * @property bool $order_locked
 * @property string $status
 * @property bool $is_orphaned
 * @property \Illuminate\Support\Carbon|null $orphaned_at
 */
final class CatalogFeature extends Model
{
    protected $table = 'larc_features';

    protected $fillable = [
        'uuid',
        'key',
        'menu_id',
        'source',
        'path',
        'path_locked',
        'order',
        'placement',
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
        static::creating(function (self $feature): void {
            if ($feature->uuid === null || $feature->uuid === '') {
                $feature->uuid = (string) Str::uuid();
            }
        });
    }

    /** @return BelongsTo<CatalogMenu, $this> */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(CatalogMenu::class, 'menu_id');
    }

    /** @return HasMany<CatalogFeatureTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(CatalogFeatureTranslation::class, 'feature_id');
    }
}
