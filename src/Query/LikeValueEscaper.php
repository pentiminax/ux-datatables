<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query;

/**
 * Escapes SQL LIKE wildcard characters in a user-supplied search term before it is wrapped
 * into a LIKE pattern.
 *
 * Without this, a term containing a literal `%` or `_` is interpreted as a wildcard instead
 * of a literal character — e.g. searching for "50% off" matches any string starting with
 * "50", any characters, then " off", rather than the literal substring "50% off". This is a
 * correctness bug, not a SQL injection risk: the term is always bound through setParameter(),
 * never interpolated into the DQL string.
 *
 * Every generated LIKE condition must add `ESCAPE '{@see self::ESCAPE_CHARACTER}'` for this
 * escaping to take effect — without it, the database still treats the doubled escape
 * character and the escaped wildcards literally rather than as an escape sequence.
 */
final class LikeValueEscaper
{
    public const string ESCAPE_CHARACTER = '\\';

    public static function escape(string $value): string
    {
        return str_replace(
            [self::ESCAPE_CHARACTER, '%', '_'],
            [self::ESCAPE_CHARACTER.self::ESCAPE_CHARACTER, self::ESCAPE_CHARACTER.'%', self::ESCAPE_CHARACTER.'_'],
            $value,
        );
    }
}
