<?php

declare(strict_types=1);

use Illuminate\Contracts\Translation\Translator;
use Laravarc\Core\I18n\ModuleTranslationLoader;
use Laravarc\Core\Module\ModuleLayout;

describe('module and shared translation loading', function () {
    it('registers module Lang as module.key and Shared Langs as shared.module.key', function () {
        $modulesPath = config('laravarc.modules_path');
        $sharedPath = CorePathResolverShared();

        $moduleRoot = $modulesPath.'/Platform/I18nDemo';
        $sharedRoot = $sharedPath.'/Platform/I18nDemo';

        foreach ([
            ModuleLayout::CONTROLLERS,
            ModuleLayout::FORM_REQUESTS,
            ModuleLayout::SERVICES,
            ModuleLayout::REPOSITORIES,
            ModuleLayout::POLICIES,
            ModuleLayout::MODELS,
            ModuleLayout::DATABASE.'/'.ModuleLayout::MIGRATIONS,
            ModuleLayout::ROUTES,
            ModuleLayout::LANG.'/en',
        ] as $folder) {
            mkdir($moduleRoot.'/'.$folder, 0777, true);
        }

        mkdir($sharedRoot.'/Langs/en', 0777, true);

        file_put_contents($moduleRoot.'/'.ModuleLayout::LANG.'/en/messages.php', <<<'PHP'
<?php

return ['hello' => 'module-hello'];
PHP);

        file_put_contents($sharedRoot.'/Langs/en/messages.php', <<<'PHP'
<?php

return ['hello' => 'shared-hello'];
PHP);

        app(\Laravarc\Core\Discovery\ModuleRegistry::class)->refresh();

        /** @var ModuleTranslationLoader $loader */
        $loader = app(ModuleTranslationLoader::class);
        $loader->load(app(Translator::class));

        $namespaces = collect($loader->discovered())->pluck('namespace')->all();

        expect($namespaces)->toContain('platform.i18n-demo')
            ->and($namespaces)->toContain('shared.platform.i18n-demo')
            ->and(__('platform.i18n-demo::messages.hello'))->toBe('module-hello')
            ->and(__('shared.platform.i18n-demo::messages.hello'))->toBe('shared-hello');
    });
});

function CorePathResolverShared(): string
{
    return \Laravarc\Core\Support\CorePathResolver::resolve(
        (string) config('laravarc.shared_path', app_path('Shared')),
    );
}
