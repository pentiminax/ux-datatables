<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query;

use Pentiminax\UX\DataTables\Query\BooleanSearchTerm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(BooleanSearchTerm::class)]
final class BooleanSearchTermTest extends TestCase
{
    #[Test]
    #[DataProvider('accepted_terms')]
    public function it_parses_a_boolean_literal(string $value, bool $expected): void
    {
        $this->assertSame($expected, BooleanSearchTerm::normalize($value));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function accepted_terms(): iterable
    {
        yield 'true' => ['true', true];
        yield 'false' => ['false', false];
        yield '1' => ['1', true];
        yield '0' => ['0', false];
        yield 'yes' => ['yes', true];
        yield 'no' => ['no', false];
        yield 'padded false' => ['  false  ', false];
    }

    #[Test]
    #[DataProvider('rejected_terms')]
    public function it_rejects_a_non_boolean_literal(string $value): void
    {
        $this->assertNull(BooleanSearchTerm::normalize($value));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rejected_terms(): iterable
    {
        yield 'empty string' => [''];
        yield 'blank string' => ['   '];
        yield 'garbage text' => ['not-a-boolean'];
        yield 'yes please' => ['yes please'];
        yield '2' => ['2'];
    }
}
