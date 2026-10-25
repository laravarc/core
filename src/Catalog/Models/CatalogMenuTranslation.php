<?php

declare(strict_types=1);

namespace Laravarc\Core\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $menu_id
 * @property string $locale
 * @property string $label
 */
final class CatalogMenuTranslation extends Model
{
    public $timestamps = false;

    protected $table = 'larc_menu_translations';

    protected $fillable = [
        'menu_id',
        'locale',
        'label',
    ];

    /** @return BelongsTo<CatalogMenu, $this> */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(CatalogMenu::class, 'menu_id');
    }
}
