<?php

declare(strict_types=1);

namespace Laravarc\Core\Generation;

final class MetadataGenerationVariables
{
    /**
     * @return array<string, string>
     */
    public static function forContext(GenerationContext $context): array
    {
        $moduleKey = $context->moduleKey;

        return [
            'menuKey' => $moduleKey.'.index',
            'menuLabel' => 'menu.'.$moduleKey,
            'menuOrder' => '0',
            'featureKey' => $moduleKey.'.manage',
            'featureLabel' => 'feature.'.$moduleKey.'.manage',
            'featurePlacement' => 'tab',
            'featureOrder' => '0',
        ];
    }
}
