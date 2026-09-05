<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Laravarc\Core\Metadata\CoreListenerRegistrar;
use Laravarc\Core\Module\ModuleLayout;

describe('compiled listener bindings', function () {
    it('registers listeners from Core ListenTo attributes with Eventer installed', function () {
        expect(class_exists(\Laravarc\Eventer\Facades\Eventer::class))->toBeTrue();

        $modulesPath = config('laravarc.modules_path');
        $sharedPath = $modulesPath.'/_shared';
        config(['laravarc.shared_path' => $sharedPath]);
        $moduleRoot = $modulesPath.'/Platform/Listener';
        $namespace = 'App\\Modules\\Platform\\Listener';
        $sharedEventsPath = $sharedPath.'/Platform/User/Events';
        mkdir($sharedEventsPath, 0777, true);
        mkdir($moduleRoot.'/'.ModuleLayout::CONTROLLERS, 0777, true);
        mkdir($moduleRoot.'/'.ModuleLayout::LISTENERS, 0777, true);

        file_put_contents($sharedEventsPath.'/UserDeletedEvent.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Shared\Platform\User\Events;

final class UserDeletedEvent
{
    public function __construct(public readonly int $userId) {}
}
PHP);

        file_put_contents($moduleRoot.'/Controllers/ListenerController.php', <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace}\\Controllers;

final class ListenerController {}
PHP);

        file_put_contents($moduleRoot.'/Listeners/RecordUserDeletedListener.php', <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace}\\Listeners;

use App\\Shared\\Platform\\User\\Events\\UserDeletedEvent;
use Laravarc\\Core\\Metadata\\Attributes\\ListenTo;

#[ListenTo(UserDeletedEvent::class)]
final class RecordUserDeletedListener
{
    public static ?int \$receivedUserId = null;

    public function handle(UserDeletedEvent \$event): void
    {
        self::\$receivedUserId = \$event->userId;
    }
}
PHP);

        require_once $sharedEventsPath.'/UserDeletedEvent.php';

        expect(Artisan::call('laravarc:cache', ['action' => 'refresh']))->toBe(0)
            ->and(Artisan::call('laravarc:metadata', ['action' => 'compile', '--module' => 'platform/listener']))->toBe(0);

        $listeners = app(\Laravarc\Core\Contracts\MetadataReader::class)
            ->artifact()
            ->modules['platform.listener']['listeners'] ?? [];

        expect($listeners)->toHaveCount(1)
            ->and($listeners[0]['event'])->toBe('App\\Shared\\Platform\\User\\Events\\UserDeletedEvent')
            ->and($listeners[0]['listener'])->toBe($namespace.'\\Listeners\\RecordUserDeletedListener');

        require_once $moduleRoot.'/Listeners/RecordUserDeletedListener.php';

        app(CoreListenerRegistrar::class)->register();

        Event::dispatch(new \App\Shared\Platform\User\Events\UserDeletedEvent(99));

        expect(\App\Modules\Platform\Listener\Listeners\RecordUserDeletedListener::$receivedUserId)->toBe(99);
    });

    it('registers listeners from Core ListenTo attributes without depending on Eventer runtime', function () {
        $modulesPath = config('laravarc.modules_path');
        $sharedPath = $modulesPath.'/_shared_no_eventer';
        config(['laravarc.shared_path' => $sharedPath]);
        $moduleRoot = $modulesPath.'/Platform/NoEventer';
        $namespace = 'App\\Modules\\Platform\\NoEventer';
        $sharedEventsPath = $sharedPath.'/Platform/User/Events';
        mkdir($sharedEventsPath, 0777, true);
        mkdir($moduleRoot.'/'.ModuleLayout::CONTROLLERS, 0777, true);
        mkdir($moduleRoot.'/'.ModuleLayout::LISTENERS, 0777, true);

        file_put_contents($sharedEventsPath.'/UserDeletedEvent.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Shared\Platform\User\Events;

final class UserDeletedEvent
{
    public function __construct(public readonly int $userId) {}
}
PHP);

        file_put_contents($moduleRoot.'/Controllers/NoEventerController.php', <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace}\\Controllers;

final class NoEventerController {}
PHP);

        file_put_contents($moduleRoot.'/Listeners/RecordUserDeletedListener.php', <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace}\\Listeners;

use App\\Shared\\Platform\\User\\Events\\UserDeletedEvent;
use Laravarc\\Core\\Metadata\\Attributes\\ListenTo;

#[ListenTo(UserDeletedEvent::class)]
final class RecordUserDeletedListener
{
    public static ?int \$receivedUserId = null;

    public function handle(UserDeletedEvent \$event): void
    {
        self::\$receivedUserId = \$event->userId;
    }
}
PHP);

        if (! class_exists(\App\Shared\Platform\User\Events\UserDeletedEvent::class)) {
            require_once $sharedEventsPath.'/UserDeletedEvent.php';
        }

        expect(Artisan::call('laravarc:cache', ['action' => 'refresh']))->toBe(0)
            ->and(Artisan::call('laravarc:metadata', ['action' => 'compile', '--module' => 'platform/no-eventer']))->toBe(0);

        $listeners = app(\Laravarc\Core\Contracts\MetadataReader::class)
            ->artifact()
            ->modules['platform.no-eventer']['listeners'] ?? [];

        expect($listeners)->toHaveCount(1)
            ->and($listeners[0]['event'])->toBe('App\\Shared\\Platform\\User\\Events\\UserDeletedEvent')
            ->and($listeners[0]['listener'])->toBe($namespace.'\\Listeners\\RecordUserDeletedListener');

        require_once $moduleRoot.'/Listeners/RecordUserDeletedListener.php';

        app(CoreListenerRegistrar::class)->register();

        Event::dispatch(new \App\Shared\Platform\User\Events\UserDeletedEvent(77));

        expect(\App\Modules\Platform\NoEventer\Listeners\RecordUserDeletedListener::$receivedUserId)->toBe(77);
    });
});
