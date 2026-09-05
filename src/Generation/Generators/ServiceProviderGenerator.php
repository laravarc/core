<?php

declare(strict_types=1);

namespace Laravarc\Core\Generation\Generators;

use Illuminate\Support\Str;
use Laravarc\Core\Generation\GenerationContext;
use Laravarc\Core\Generation\GeneratorName;

final class ServiceProviderGenerator extends AbstractStubGenerator
{
    use SupportsSelectedGenerator;

    public function name(): string
    {
        return GeneratorName::SERVICE_PROVIDER;
    }

    public function supports(GenerationContext $context): bool
    {
        return $this->isSelected($context);
    }

    protected function relativePath(GenerationContext $context): string
    {
        return 'Providers/'.$this->basename($context).'ServiceProvider.php';
    }

    protected function variables(GenerationContext $context): array
    {
        $basename = $this->basename($context);
        $extensionClass = $basename.'CoreExtension';

        return [
            'moduleNamespace' => $context->moduleNamespace,
            'modulePath' => $context->modulePath,
            'providerClass' => $basename.'ServiceProvider',
            'extensionClass' => $extensionClass,
            'extensionFqcn' => $context->moduleNamespace.'\\Extensions\\'.$extensionClass,
            'extensionKey' => str_replace('/', '.', Str::lower($context->modulePath)),
            'extensionRegisterBlock' => $context->withExtension
                ? "        config()->push('laravarc.extensions', {$extensionClass}::class);"
                : '',
            'extensionUseStatement' => $context->withExtension
                ? "use {$context->moduleNamespace}\\Extensions\\{$extensionClass};"
                : '',
        ];
    }

    protected function stubName(GenerationContext $context): string
    {
        return $context->withExtension
            ? 'service-provider.with-extension'
            : 'service-provider';
    }

    public function generate(GenerationContext $context): array
    {
        $template = (string) file_get_contents($context->namedStubPath($this->stubName($context)));

        return [
            new \Laravarc\Core\Generation\GeneratedFile(
                relativePath: $this->relativePath($context),
                contents: \Laravarc\Core\Generation\StubRenderer::render($template, $this->variables($context)),
            ),
        ];
    }

    private function basename(GenerationContext $context): string
    {
        $segments = array_values(array_filter(explode('/', $context->modulePath)));

        return Str::studly((string) ($segments[array_key_last($segments)] ?? $context->moduleName));
    }
}
