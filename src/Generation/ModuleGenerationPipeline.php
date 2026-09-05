<?php

declare(strict_types=1);

namespace Laravarc\Core\Generation;

use Illuminate\Filesystem\Filesystem;
use Laravarc\Core\Contracts\ModuleGenerator;
use Laravarc\Core\Extensions\ExtensionHook;
use Laravarc\Core\Extensions\ExtensionManager;
use Throwable;

final class ModuleGenerationPipeline
{
    /**
     * @param  list<ModuleGenerator>  $generators
     */
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly array $generators,
        private readonly ?ExtensionManager $extensions = null,
    ) {}

    public function run(GenerationContext $context, bool $dryRun = false): GenerationRunResult
    {
        $this->extensions?->dispatch(ExtensionHook::GenerationBefore, $context);

        $generators = [
            ...$this->generators,
            ...($this->extensions?->generators() ?? []),
        ];

        $written = [];
        $generatedGenerators = [];
        $failures = [];

        foreach ($generators as $generator) {
            if (! $generator->supports($context)) {
                continue;
            }

            try {
                $generatorFiles = [];

                foreach ($generator->generate($context) as $file) {
                    $generatorFiles[] = $file->relativePath;

                    if ($dryRun) {
                        continue;
                    }

                    $absolutePath = $file->absolutePath
                        ?? (rtrim($context->filesystemRoot, '/').'/'.$file->relativePath);
                    $directory = dirname($absolutePath);

                    if (! $this->filesystem->isDirectory($directory)) {
                        $this->filesystem->makeDirectory($directory, 0755, true);
                    }

                    $this->filesystem->put($absolutePath, $file->contents);
                    $written[] = $file->relativePath;
                }

                if ($generatorFiles !== []) {
                    $generatedGenerators[] = $generator->name();
                }
            } catch (Throwable $exception) {
                $failures[] = new GenerationFailure(
                    generator: $generator->name(),
                    message: $exception->getMessage(),
                );
            }
        }

        $result = new GenerationRunResult(
            writtenFiles: $written,
            generatedGenerators: $generatedGenerators,
            failures: $failures,
            warnings: $context->config['warnings'] ?? [],
        );

        $this->extensions?->dispatch(ExtensionHook::GenerationAfter, $result);

        return $result;
    }
}
