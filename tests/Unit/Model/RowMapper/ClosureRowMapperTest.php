<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model\RowMapper;

use Pentiminax\UX\DataTables\RowMapper\ClosureRowMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ClosureRowMapper::class)]
final class ClosureRowMapperTest extends TestCase
{
    public static function rowProvider(): iterable
    {
        yield 'array item' => [
            static fn (array $item): array => ['id' => $item['id'], 'name' => strtoupper($item['name'])],
            ['id' => 1, 'name' => 'john'],
            ['id' => 1, 'name' => 'JOHN'],
        ];

        yield 'object item' => [
            static fn (object $item): array => ['id' => $item->id, 'name' => ucfirst($item->name)],
            (object) ['id' => 2, 'name' => 'doe'],
            ['id' => 2, 'name' => 'Doe'],
        ];
    }

    /**
     * @param array<string, mixed> $expected
     */
    #[Test]
    #[DataProvider('rowProvider')]
    public function it_maps_rows_through_the_closure(\Closure $mapper, mixed $item, array $expected): void
    {
        $this->assertSame($expected, (new ClosureRowMapper($mapper))->map($item));
    }
}
