<?php

declare(strict_types=1);

namespace Laravarc\Core\Convention;

use Laravarc\Core\Convention\Exceptions\InvalidLayerException;
use Laravarc\Core\Module\ModuleLayout;

enum Layer: string
{
    case Controller = 'controller';
    case FormRequest = 'form_request';
    case Service = 'service';
    case Repository = 'repository';
    case Model = 'model';
    case Policy = 'policy';
    case Resource = 'resource';
    case Event = 'event';
    case Listener = 'listener';

    public function folder(): string
    {
        return match ($this) {
            self::Controller => ModuleLayout::CONTROLLERS,
            self::FormRequest => ModuleLayout::FORM_REQUESTS,
            self::Service => ModuleLayout::SERVICES,
            self::Repository => ModuleLayout::REPOSITORIES,
            self::Model => ModuleLayout::MODELS,
            self::Policy => ModuleLayout::POLICIES,
            self::Resource => ModuleLayout::RESOURCES,
            self::Event => ModuleLayout::EVENTS,
            self::Listener => ModuleLayout::LISTENERS,
        };
    }

    public static function fromString(string $value): self
    {
        $normalized = strtolower(str_replace(['-', ' '], '_', trim($value)));

        $resolved = self::tryFrom($normalized);

        if ($resolved === null) {
            $resolved = match ($normalized) {
                'formrequest', 'formrequests' => self::FormRequest,
                default => null,
            };
        }

        if ($resolved === null) {
            throw new InvalidLayerException(sprintf('Unknown layer role "%s".', $value));
        }

        return $resolved;
    }
}
