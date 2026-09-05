<?php

declare(strict_types=1);

namespace Laravarc\Core\Contracts;

use Illuminate\Http\Request;

interface PolicyEvaluator
{
    public function authorize(Request $request, ?string $abilityOverride = null): void;
}
