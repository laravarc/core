<?php

declare(strict_types=1);

namespace Laravarc\Core\Commands\Generation;

use Illuminate\Console\OutputStyle;

final class GenerationSummaryPrinter
{
    /**
     * @param  list<GenerationSummaryLine>  $lines
     */
    public function print(OutputStyle $output, string $modulePath, array $lines): void
    {
        $output->writeln('');
        $output->writeln('Module: '.$modulePath);

        $generated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($lines as $line) {
            match ($line->status) {
                'generated' => $this->printGenerated($output, $line->label),
                'failed' => $this->printFailed($output, $line->label),
                default => $this->printSkipped($output, $line->label, $line->reason ?? 'preset'),
            };

            match ($line->status) {
                'generated' => $generated++,
                'failed' => $failed++,
                default => $skipped++,
            };
        }

        $output->writeln('');
        $output->writeln('Generated: '.$generated);
        $output->writeln('Skipped : '.$skipped);
        $output->writeln('Failed  : '.$failed);
    }

    private function printGenerated(OutputStyle $output, string $label): void
    {
        $output->writeln('<info>✓</info> '.$label.' generated');
    }

    private function printSkipped(OutputStyle $output, string $label, string $reason): void
    {
        $output->writeln('- '.$label.' skipped ('.$reason.')');
    }

    private function printFailed(OutputStyle $output, string $label): void
    {
        $output->writeln('<error>✗</error> '.$label.' failed');
    }
}
