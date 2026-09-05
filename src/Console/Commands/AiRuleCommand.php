<?php

declare(strict_types=1);

namespace Laravarc\Core\Console\Commands;

use Illuminate\Console\Command;
use Laravarc\Core\AiRules\AiRulesPackageResolver;
use Throwable;

final class AiRuleCommand extends Command
{
    protected $signature = 'laravarc:ai-rule
                            {action : version}
                            {--json : Output machine-readable JSON}';

    protected $description = 'Inspect Laravarc AI rules package metadata';

    /** @var list<string> */
    protected $aliases = ['larc:ai-rule'];

    public function handle(AiRulesPackageResolver $resolver): int
    {
        $action = (string) $this->argument('action');

        if ($action !== 'version') {
            $this->error('Supported actions: version.');

            return self::FAILURE;
        }

        try {
            $packagePath = $resolver->packagePath();
            $manifest = $resolver->manifest();

            if ($this->option('json')) {
                $this->line((string) json_encode(
                    $manifest->toArray($packagePath),
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
                ));

                return self::SUCCESS;
            }

            $this->line('Laravarc AI Rules');
            $this->line('Package: '.$manifest->package);
            $this->line('Rules version: '.$manifest->rulesVersion);
            $this->line('Package path: '.$packagePath);
            $this->newLine();
            $this->line('Rules:');

            foreach ($manifest->rules as $rule) {
                $this->line(sprintf(
                    '  %s (%s) %s',
                    $rule->id,
                    $rule->version,
                    $rule->file,
                ));
            }

            $this->newLine();
            $this->line('Persist these rules to your AI storage and compare arc_rule_version before each session.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
