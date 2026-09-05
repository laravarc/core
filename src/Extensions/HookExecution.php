<?php

declare(strict_types=1);

namespace Laravarc\Core\Extensions;

enum HookExecution
{
    case Exclusive;
    case Broadcast;
    case Chain;
}
