<?php

namespace Pentiminax\UX\DataTables\ApiPlatform;

final class PropertyNameHumanizer
{
    /**
     * Convert a camelCase or snake_case property name into a human-readable label.
     */
    public function humanize(string $name): string
    {
        $label = str_replace(['_', '-'], ' ', $name);
        $label = preg_replace('/(?<!^)([A-Z])/', ' $1', $label);
        $label = trim($label ?? '');
        $label = ucwords($label);
        $label = preg_replace('/\bId\b/', 'ID', $label);

        return '' === $label ? $name : $label;
    }
}
