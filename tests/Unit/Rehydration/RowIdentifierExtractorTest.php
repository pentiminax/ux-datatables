<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Rehydration;

use Pentiminax\UX\DataTables\Rehydration\RowIdentifierExtractor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(RowIdentifierExtractor::class)]
final class RowIdentifierExtractorTest extends TestCase
{
    /**
     * @param array<string, mixed> $row
     */
    #[Test]
    #[DataProvider('provideRows')]
    public function it_extracts_the_row_identifier(array $row, int|string|null $expected): void
    {
        $this->assertSame($expected, (new RowIdentifierExtractor())->extract($row));
    }

    public static function provideRows(): iterable
    {
        yield 'integer id' => [['id' => 7], 7];

        yield 'string id' => [['id' => 'abc'], 'abc'];

        yield 'last segment of an IRI' => [['@id' => '/api/users/7'], '7'];

        yield 'no identifier at all' => [['email' => 'user@example.com'], null];

        yield 'blank IRI' => [['@id' => '   '], null];
    }
}
