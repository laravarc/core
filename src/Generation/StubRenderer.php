<?php

declare(strict_types=1);

namespace Laravarc\Core\Generation;

final class StubRenderer
{
    /**
     * @param  array<string, string>  $variables
     */
    public static function render(string $template, array $variables): string
    {
        $search = [];
        $replace = [];

        foreach ($variables as $key => $value) {
            $search[] = '{{ '.$key.' }}';
            $replace[] = $value;
        }

        return str_replace($search, $replace, $template);
    }
}
