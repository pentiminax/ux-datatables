<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query;

use Pentiminax\UX\DataTables\Query\NumericSearchTerm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(NumericSearchTerm::class)]
final class NumericSearchTermTest extends TestCase
{
    #[Test]
    #[DataProvider('accepted_terms')]
    public function it_accepts_a_numeric_literal(string $value, string $doctrineType, string $expected): void
    {
        $this->assertSame($expected, NumericSearchTerm::normalize($value, $doctrineType));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function accepted_terms(): iterable
    {
        yield 'integer' => ['42', 'integer', '42'];
        yield 'negative integer' => ['-7', 'smallint', '-7'];
        yield 'padded integer' => ['  42  ', 'integer', '42'];
        yield 'bigint' => ['9223372036854775807', 'bigint', '9223372036854775807'];
        yield 'float' => ['19.99', 'float', '19.99'];
        yield 'decimal' => ['0.5', 'decimal', '0.5'];
        yield 'scientific float' => ['1e2', 'float', '1e2'];
        yield 'zero' => ['0', 'integer', '0'];
        yield 'leading plus integer' => ['+42', 'integer', '42'];
        yield 'leading plus float' => ['+19.99', 'float', '19.99'];
        yield 'padded zeros integer' => ['007', 'integer', '7'];
        yield 'negative zero' => ['-0', 'integer', '0'];
        yield 'smallint bound' => ['-32768', 'smallint', '-32768'];
        yield 'integer bound' => ['2147483647', 'integer', '2147483647'];
    }

    #[Test]
    #[DataProvider('rejected_terms')]
    public function it_rejects_a_non_numeric_literal(string $value, string $doctrineType): void
    {
        $this->assertNull(NumericSearchTerm::normalize($value, $doctrineType));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function rejected_terms(): iterable
    {
        yield 'empty integer' => ['', 'integer'];
        yield 'blank integer' => ['   ', 'integer'];
        yield 'letters on integer' => ['abc', 'integer'];
        yield 'decimal on integer' => ['1.5', 'integer'];
        yield 'suffix on integer' => ['42abc', 'integer'];
        yield 'letters on float' => ['abc', 'float'];
        yield 'empty float' => ['', 'float'];
        yield 'nan' => ['NAN', 'float'];
        yield 'inf' => ['INF', 'float'];
        yield 'above smallint' => ['32768', 'smallint'];
        yield 'below smallint' => ['-32769', 'smallint'];
        yield 'above integer' => ['2147483648', 'integer'];
        yield 'above bigint' => ['9223372036854775808', 'bigint'];
        yield 'below bigint' => ['-9223372036854775809', 'bigint'];
        yield 'far above bigint' => ['99999999999999999999999', 'bigint'];
    }
}
