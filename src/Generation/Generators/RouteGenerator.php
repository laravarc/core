<?php

declare(strict_types=1);

namespace Laravarc\Core\Generation\Generators;

use Illuminate\Support\Str;
use Laravarc\Core\Generation\GeneratedFile;
use Laravarc\Core\Generation\GenerationContext;
use Laravarc\Core\Generation\GeneratorName;
use Laravarc\Core\Generation\StubRenderer;
use Laravarc\Core\Surfacer\RootSurfaceLocator;

final class RouteGenerator extends AbstractStubGenerator
{
    use SupportsSelectedGenerator;

    public function name(): string
    {
        return GeneratorName::ROUTE;
    }

    public function supports(GenerationContext $context): bool
    {
        return $this->isSelected($context) && $context->schemaSnapshot !== null;
    }

    public function generate(GenerationContext $context): array
    {
        $template = (string) file_get_contents(
            $context->presentationStack === 'blade'
                ? $context->namedStubPath('route_blade')
                : $context->stubPath($this->name()),
        );

        return [
            new GeneratedFile(
                relativePath: $this->relativePath($context),
                contents: StubRenderer::render($template, $this->variables($context)),
            ),
        ];
    }

    protected function relativePath(GenerationContext $context): string
    {
        return 'Routes/'.$context->moduleName.'Route.php';
    }

    /**
     * @return array<string, string>
     */
    protected function variables(GenerationContext $context): array
    {
        $controller = $context->classFor('controller');
        $middleware = $context->config['route_middleware'] ?? [];
        $middlewareList = is_array($middleware) ? implode(', ', array_map(
            static fn (mixed $item): string => "'".(string) $item."'",
            $middleware,
        )) : "'api'";

        $underSurface = $this->rootHasSurface($context);

        return [
            'controllerClass' => $controller['className'],
            'controllerShortName' => $controller['shortName'],
            'resourceName' => Str::kebab(Str::pluralStudly($context->moduleName)),
            'middleware' => $middlewareList,
            'moduleKey' => $context->moduleKey,
            'routeGroupOpen' => $underSurface
                ? ''
                : "Route::middleware([{$middlewareList}])->group(function (): void {",
            'routeGroupClose' => $underSurface ? '' : '});',
        ];
    }

    private function rootHasSurface(GenerationContext $context): bool
    {
        $modulesPath = (string) config('laravarc.modules_path', '');
        if ($modulesPath === '' || ! is_dir($modulesPath)) {
            return false;
        }

        $locator = new RootSurfaceLocator;
        $rootSegment = $locator->rootSegmentFromModulePath($context->modulePath);

        return $locator->hasSurface($modulesPath, $rootSegment);
    }
}
