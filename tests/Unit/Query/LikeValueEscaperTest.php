<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query;

use Pentiminax\UX\DataTables\Query\LikeValueEscaper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(LikeValueEscaper::class)]
final class LikeValueEscaperTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function values(): iterable
    {
        yield 'no special characters' => ['hello', 'hello'];
        yield 'percent sign' => ['50%', '50!%'];
        yield 'underscore' => ['a_b', 'a!_b'];
        yield 'backslash is not special' => ['a\b', 'a\b'];
        yield 'exclamation mark, the escape character itself' => ['great!', 'great!!'];
        yield 'percent, underscore, and exclamation mark together' => [
            '50%_off!sale',
            '50!%!_off!!sale',
        ];
        yield 'only wildcards' => ['%_', '!%!_'];
        yield 'exclamation mark immediately before a wildcard' => [
            '!%',
            '!!!%',
        ];
        yield 'empty string' => ['', ''];
    }

    #[Test]
    #[DataProvider('values')]
    public function it_escapes_like_wildcard_characters(string $value, string $expected): void
    {
        $this->assertSame($expected, LikeValueEscaper::escape($value));
    }

    /**
     * Demonstrates the actual bug this class fixes: without escaping, a literal wildcard in
     * the search term changes what the pattern matches instead of being matched literally.
     */
    #[Test]
    public function it_produces_a_pattern_that_matches_the_literal_term_instead_of_a_wildcard(): void
    {
        $escaped = LikeValueEscaper::escape('50% off');
        $pattern = \sprintf('%%%s%%', $escaped);

        // The escaped pattern reads back as a literal match for "50% off" bracketed by
        // wildcards this call added itself -- not "50", then anything, then " off", which
        // is what wrapping the raw, unescaped value in %...% would produce instead.
        $this->assertSame('%50!% off%', $pattern);
        $this->assertStringNotContainsString('%50%', $pattern);
    }

    /**
     * ! rather than the more conventional backslash is deliberate: MySQL's default SQL mode
     * treats a raw backslash inside a quoted string literal as escaping the closing quote,
     * so ESCAPE '\' breaks with a syntax error on every LIKE search on that platform. ! has
     * no special meaning in any supported platform's string literal syntax, so the same
     * generated ESCAPE clause is correct everywhere without detecting which database is
     * connected.
     */
    #[Test]
    public function it_uses_an_escape_character_with_no_special_meaning_in_sql_string_literals(): void
    {
        $this->assertSame('!', LikeValueEscaper::ESCAPE_CHARACTER);
    }

    #[Test]
    public function it_is_reversible_by_removing_the_escape_character(): void
    {
        // Sanity check on the escaping scheme itself for a value with no literal exclamation
        // mark of its own: removing every escape character this call introduced recovers the
        // original value exactly, confirming no wildcard or character was dropped or
        // duplicated.
        $value   = 'a_b%c';
        $escaped = LikeValueEscaper::escape($value);

        $this->assertSame($value, str_replace(LikeValueEscaper::ESCAPE_CHARACTER, '', $escaped));
    }
}
