<?php

declare(strict_types=1);

namespace Laravarc\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Throwable;

final class InstallCommand extends Command
{
    protected $signature = 'laravarc:install
                            {package : Package to scaffold (currently: eventer)}
                            {--force : Overwrite published files}';

    /** @var list<string> */
    protected $aliases = ['larc:install'];

    protected $description = 'Scaffold/publish optional Laravarc package assets (does not run Composer)';

    public function __construct(
        private readonly Filesystem $files,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $package = strtolower((string) $this->argument('package'));

        try {
            return match ($package) {
                'eventer' => $this->installEventer(),
                default => $this->unsupported($package),
            };
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function installEventer(): int
    {
        if (! class_exists(\Laravarc\Eventer\Providers\EventerServiceProvider::class)) {
            $this->error('Package laravarc/eventer is not installed.');
            $this->line('Run: composer require laravarc/eventer');
            $this->line('Then re-run: php artisan laravarc:install eventer');

            return self::FAILURE;
        }

        $source = $this->eventerConfigPath();
        $target = config_path('eventer.php');

        if (! is_file($source)) {
            $this->error(sprintf('Could not locate eventer config at [%s].', $source));

            return self::FAILURE;
        }

        if (is_file($target) && ! $this->option('force')) {
            $this->warn(sprintf('[%s] already exists. Use --force to overwrite.', $target));

            return self::SUCCESS;
        }

        $this->files->ensureDirectoryExists(dirname($target));
        $this->files->copy($source, $target);

        $this->info('Published config/eventer.php');
        $this->line('Next: configure transporters/channels, then use Eventer::dispatch($event).');

        return self::SUCCESS;
    }

    private function eventerConfigPath(): string
    {
        $vendor = base_path('vendor/laravarc/eventer/config/eventer.php');

        if (is_file($vendor)) {
            return $vendor;
        }

        // Path-repo / monorepo sibling during local development.
        $sibling = dirname(__DIR__, 4).'/eventer/config/eventer.php';

        if (is_file($sibling)) {
            return $sibling;
        }

        $reflected = new \ReflectionClass(\Laravarc\Eventer\Providers\EventerServiceProvider::class);

        return dirname($reflected->getFileName(), 3).'/config/eventer.php';
    }

    private function unsupported(string $package): int
    {
        $this->error(sprintf('Unsupported package [%s]. Supported: eventer.', $package));

        return self::FAILURE;
    }
}
