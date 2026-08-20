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
        yield 'percent sign' => ['50%', '50\%'];
        yield 'underscore' => ['a_b', 'a\_b'];
        yield 'backslash' => ['a\b', 'a\\\\b'];
        yield 'percent, underscore, and backslash together' => [
            '50%_off\sale',
            '50\%\_off\\\\sale',
        ];
        yield 'only wildcards' => ['%_', '\%\_'];
        yield 'backslash immediately before a wildcard' => [
            '\%',
            '\\\\\%',
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
        $this->assertSame('%50\% off%', $pattern);
        $this->assertStringNotContainsString('%50%', $pattern);
    }

    #[Test]
    public function it_is_reversible_by_removing_the_escape_character(): void
    {
        // Sanity check on the escaping scheme itself for a value with no literal backslash of
        // its own: removing every escape character this call introduced recovers the original
        // value exactly, confirming no wildcard or character was dropped or duplicated.
        $value   = 'a_b%c';
        $escaped = LikeValueEscaper::escape($value);

        $this->assertSame($value, str_replace(LikeValueEscaper::ESCAPE_CHARACTER, '', $escaped));
    }
}
