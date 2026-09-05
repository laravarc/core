<?php

declare(strict_types=1);

namespace Laravarc\Core\Generation\Metadata;

use Laravarc\Core\Generation\GenerationContext;
use Laravarc\Core\Generation\MetadataGenerationVariables;

final class MetadataStubComposer
{
    /**
     * @return array<string, string>
     */
    public function compose(MetadataSelection $selection, GenerationContext $context): array
    {
        if ($selection->isEmpty()) {
            return $this->emptyFragments();
        }

        $variables = MetadataGenerationVariables::forContext($context);
        $uses = [];
        $classAttributes = [];

        if ($selection->has(MetadataAttribute::Menu)) {
            $uses[] = 'use Laravarc\\Core\\Metadata\\Attributes\\Menu;';
            $classAttributes[] = sprintf(
                "#[Menu(key: '%s', label: '%s', order: %s)]",
                $variables['menuKey'],
                $variables['menuLabel'],
                $variables['menuOrder'],
            );
        }

        if ($selection->has(MetadataAttribute::Feature)) {
            $uses[] = 'use Laravarc\\Core\\Metadata\\Attributes\\Feature;';
            $classAttributes[] = sprintf(
                "#[Feature(key: '%s', label: '%s', menu: '%s', placement: '%s', order: %s)]",
                $variables['featureKey'],
                $variables['featureLabel'],
                $variables['menuKey'],
                $variables['featurePlacement'],
                $variables['featureOrder'],
            );
        }

        if ($selection->has(MetadataAttribute::Public)) {
            $uses[] = 'use Laravarc\\Core\\Metadata\\Attributes\\PublicAccess as Public;';
            array_unshift($classAttributes, '#[Public]');
        }

        if ($selection->has(MetadataAttribute::Policy)) {
            $uses[] = 'use Laravarc\\Core\\Metadata\\Attributes\\Policy;';
        }

        sort($uses);

        return array_merge($variables, [
            'metadataUses' => $uses === [] ? '' : implode(PHP_EOL, $uses).PHP_EOL,
            'metadataClassAttributes' => $classAttributes === []
                ? ''
                : implode(PHP_EOL, $classAttributes).PHP_EOL,
            'indexPolicyAttribute' => $this->policyAttribute($selection, 'viewAny'),
            'createPolicyAttribute' => $this->policyAttribute($selection, 'create'),
            'showPolicyAttribute' => $this->policyAttribute($selection, 'view'),
            'editPolicyAttribute' => $this->policyAttribute($selection, 'update'),
            'storePolicyAttribute' => $this->policyAttribute($selection, 'create'),
            'updatePolicyAttribute' => $this->policyAttribute($selection, 'update'),
            'destroyPolicyAttribute' => $this->policyAttribute($selection, 'delete'),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function emptyFragments(): array
    {
        return [
            'metadataUses' => '',
            'metadataClassAttributes' => '',
            'indexPolicyAttribute' => '',
            'createPolicyAttribute' => '',
            'showPolicyAttribute' => '',
            'editPolicyAttribute' => '',
            'storePolicyAttribute' => '',
            'updatePolicyAttribute' => '',
            'destroyPolicyAttribute' => '',
            'menuKey' => '',
            'menuLabel' => '',
            'menuOrder' => '',
            'featureKey' => '',
            'featureLabel' => '',
            'featurePlacement' => '',
            'featureOrder' => '',
        ];
    }

    private function policyAttribute(MetadataSelection $selection, string $ability): string
    {
        if (! $selection->has(MetadataAttribute::Policy)) {
            return '';
        }

        return sprintf('    #[Policy(ability: \'%s\')]', $ability).PHP_EOL;
    }
}
