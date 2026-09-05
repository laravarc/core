<?php

declare(strict_types=1);

namespace Laravarc\Core\Commands\Generation;

use Illuminate\Console\OutputStyle;
use Laravarc\Core\Commands\Services\ModuleRelocatePlan;

final class ModuleRelocatePreviewPrinter
{
    public function print(OutputStyle $output, ModuleRelocatePlan $plan): void
    {
        $output->writeln('<info>Files to move:</info>');
        $output->writeln(sprintf(
            '  %s/* → %s/*',
            $this->displayPath($plan->source->rootPath),
            $this->displayPath($plan->target->rootPath),
        ));
        $output->writeln(sprintf('  (%d file(s))', count($plan->filesToMove)));
        $output->newLine();

        $output->writeln('<info>Namespace replacements inside moved module:</info>');
        $output->writeln(sprintf(
            '  %s\\* → %s\\*',
            $plan->oldNamespace(),
            $plan->newNamespace(),
        ));
        $output->writeln(sprintf('  (%d file(s))', count($plan->internalReplacementFiles)));
        $output->newLine();

        $output->writeln('<info>Cross-module references found (outside moved module):</info>');

        if ($plan->crossModuleReplacementFiles === []) {
            $output->writeln('  (none)');
        } else {
            foreach ($plan->crossModuleReplacementFiles as $file) {
                $output->writeln('  '.$this->displayPath($file));
                $snippet = $this->firstReferenceSnippet($file, $plan->oldNamespace());

                if ($snippet !== null) {
                    $output->writeln('    '.$snippet.' → will be updated');
                }
            }
        }

        if ($plan->routeFileRenameFrom !== null && $plan->routeFileRenameTo !== null) {
            $output->newLine();
            $output->writeln('<info>Route file rename:</info>');
            $output->writeln(sprintf(
                '  Routes/%s → Routes/%s',
                $plan->routeFileRenameFrom,
                $plan->routeFileRenameTo,
            ));
        }

        $output->newLine();
        $output->writeln('<comment>Untouched (will NOT change):</comment>');
        $output->writeln('  - Database table name(s)');
        $output->writeln('  - Migration file contents');
        $output->writeln('  - Existing data');
    }

    private function firstReferenceSnippet(string $file, string $namespace): ?string
    {
        $contents = (string) file_get_contents($file);

        if (! preg_match('/.*'.preg_quote($namespace, '/').'(?=\\\\|;|::).*/', $contents, $matches)) {
            return null;
        }

        return trim($matches[0]);
    }

    private function displayPath(string $path): string
    {
        $base = realpath(base_path()) ?: base_path();
        $normalizedBase = str_replace('\\', '/', $base);
        $normalizedPath = str_replace('\\', '/', $path);

        if (str_starts_with($normalizedPath, $normalizedBase.'/')) {
            return substr($normalizedPath, strlen($normalizedBase) + 1);
        }

        return $normalizedPath;
    }
}
