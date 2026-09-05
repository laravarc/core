<?php

declare(strict_types=1);

use Laravarc\Core\Discovery\ModuleManifestEntry;
use Laravarc\Core\Metadata\ListenerMetadataReader;
use Laravarc\Core\Metadata\ModuleClassDiscoverer;
use Laravarc\Core\Metadata\Exceptions\MetadataCompileException;

describe('ListenerMetadataReader', function () {
    it('discovers listener bindings from Core ListenTo attributes', function () {
        $moduleRoot = sys_get_temp_dir().'/arc-listener-reader-'.uniqid('', true);
        $namespace = 'App\\Modules\\Order';
        $listenersPath = $moduleRoot.'/Listeners';
        mkdir($listenersPath, 0777, true);

        $eventFile = sys_get_temp_dir().'/arc-listener-event-'.uniqid('', true).'.php';
        file_put_contents($eventFile, <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Shared\User\Events;

final class UserDeletedEvent
{
    public function __construct(public readonly int $userId) {}
}
PHP);
        require_once $eventFile;

        file_put_contents($listenersPath.'/CleanupOrdersListener.php', <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace}\\Listeners;

use App\\Shared\\User\\Events\\UserDeletedEvent;
use Laravarc\\Core\\Metadata\\Attributes\\ListenTo;

#[ListenTo(UserDeletedEvent::class)]
final class CleanupOrdersListener
{
    public function handle(UserDeletedEvent \$event): void {}
}
PHP);

        $reader = new ListenerMetadataReader(new ModuleClassDiscoverer);
        $entry = new ModuleManifestEntry(
            path: 'order',
            key: 'order',
            namespace: $namespace,
            rootPath: $moduleRoot,
            discoveredAt: now()->toIso8601String(),
        );

        expect($reader->readModule($entry))->toBe([
            [
                'event' => 'App\\Shared\\User\\Events\\UserDeletedEvent',
                'listener' => $namespace.'\\Listeners\\CleanupOrdersListener',
            ],
        ]);
    });

    it('rejects listeners without handle method', function () {
        $moduleRoot = sys_get_temp_dir().'/arc-listener-invalid-'.uniqid('', true);
        $namespace = 'App\\Modules\\Invalid';
        $listenersPath = $moduleRoot.'/Listeners';
        mkdir($listenersPath, 0777, true);

        file_put_contents($listenersPath.'/BrokenListener.php', <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace}\\Listeners;

use Laravarc\\Core\\Metadata\\Attributes\\ListenTo;

#[ListenTo(\\stdClass::class)]
final class BrokenListener {}
PHP);

        $reader = new ListenerMetadataReader(new ModuleClassDiscoverer);
        $entry = new ModuleManifestEntry(
            path: 'invalid',
            key: 'invalid',
            namespace: $namespace,
            rootPath: $moduleRoot,
            discoveredAt: now()->toIso8601String(),
        );

        expect(fn () => $reader->readModule($entry))
            ->toThrow(MetadataCompileException::class);
    });
});
