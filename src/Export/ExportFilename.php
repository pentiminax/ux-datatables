<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Export;

use Pentiminax\UX\DataTables\Enum\ExportFormat;

final class ExportFilename
{
    public static function resolve(?string $filename, string $tableClass, ExportFormat $format): string
    {
        $candidate = $filename ?? self::slugFromClass($tableClass);
        $candidate = str_replace("\0", '', $candidate);
        $candidate = basename(str_replace('\\', '/', $candidate));
        $candidate = trim($candidate);

        if ('' === $candidate || '.' === $candidate || '..' === $candidate) {
            $candidate = self::slugFromClass($tableClass);
        }

        $extension = '.'.$format->extension();

        if (!str_ends_with(strtolower($candidate), $extension)) {
            $candidate .= $extension;
        }

        return $candidate;
    }

    private static function slugFromClass(string $class): string
    {
        $short = false !== ($pos = strrpos($class, '\\')) ? substr($class, $pos + 1) : $class;
        $slug  = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $short));

        return trim($slug, '-') ?: 'export';
    }
}
