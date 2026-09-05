<?php

declare(strict_types=1);

namespace Laravarc\Core\Extensions;

enum ExtensionHook: string
{
    case RenderDispatch = 'render:dispatch';
    case AfterModuleGenerated = 'module:after';
    case GenerationBefore = 'generation:before';
    case GenerationAfter = 'generation:after';
    case MetadataCompileBefore = 'metadata:compile:before';
    case MetadataCompileAfter = 'metadata:compile:after';
    case CacheRefresh = 'cache:refresh';
    case CacheClear = 'cache:clear';

    public function executionType(): HookExecution
    {
        return match ($this) {
            self::RenderDispatch => HookExecution::Exclusive,
            self::AfterModuleGenerated,
            self::GenerationBefore,
            self::GenerationAfter,
            self::MetadataCompileBefore,
            self::MetadataCompileAfter,
            self::CacheRefresh,
            self::CacheClear => HookExecution::Broadcast,
        };
    }
}
