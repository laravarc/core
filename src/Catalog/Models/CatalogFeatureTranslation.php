<?php

declare(strict_types=1);

namespace Laravarc\Core\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $feature_id
 * @property string $locale
 * @property string $label
 */
final class CatalogFeatureTranslation extends Model
{
    public $timestamps = false;

    protected $table = 'larc_feature_translations';

    protected $fillable = [
        'feature_id',
        'locale',
        'label',
    ];

    /** @return BelongsTo<CatalogFeature, $this> */
    public function feature(): BelongsTo
    {
        return $this->belongsTo(CatalogFeature::class, 'feature_id');
    }
}
