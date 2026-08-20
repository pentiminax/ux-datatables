<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query;

use Pentiminax\UX\DataTables\Query\DateSearchTerm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DateSearchTerm::class)]
final class DateSearchTermTest extends TestCase
{
    #[Test]
    #[DataProvider('parsable_terms')]
    public function it_parses_a_well_formed_date_string(string $value, string $expectedFormat): void
    {
        $result = DateSearchTerm::normalize($value);

        $this->assertInstanceOf(\DateTimeImmutable::class, $result);
        $this->assertSame($expectedFormat, $result->format('Y-m-d'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function parsable_terms(): iterable
    {
        yield 'iso date' => ['2026-08-19', '2026-08-19'];
        yield 'iso datetime' => ['2026-08-19 14:30:00', '2026-08-19'];
        yield 'padded date' => ['  2026-08-19  ', '2026-08-19'];
        yield 'slash date' => ['2026/08/19', '2026-08-19'];
    }

    #[Test]
    #[DataProvider('unparsable_terms')]
    public function it_rejects_a_value_that_does_not_parse_as_a_date(string $value): void
    {
        $this->assertNull(DateSearchTerm::normalize($value));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unparsable_terms(): iterable
    {
        yield 'empty string' => [''];
        yield 'blank string' => ['   '];
        yield 'garbage text' => ['not-a-date'];
        yield 'malformed punctuation' => ['2026--08~~19'];
    }
}
