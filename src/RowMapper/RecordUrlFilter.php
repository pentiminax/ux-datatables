<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\RowMapper;

/**
 * Keeps only URLs safe to navigate to: a path starting with a single "/", or an http(s) URL.
 */
final class RecordUrlFilter
{
    private const string SAFE_URL = '#^(?:/(?![/\\\\])|https?://[^/?\\#\\s]+)[^\\s\\x00-\\x1F\\x7F\\\\]*$#iD';

    public static function filter(mixed $url): ?string
    {
        if (!\is_string($url) || 1 !== preg_match(self::SAFE_URL, $url)) {
            return null;
        }

        return $url;
    }
}
