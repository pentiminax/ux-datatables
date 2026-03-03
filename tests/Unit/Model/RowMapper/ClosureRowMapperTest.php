<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model\RowMapper;

use Pentiminax\UX\DataTables\RowMapper\ClosureRowMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ClosureRowMapper::class)]
final class ClosureRowMapperTest extends TestCase
{
    #[Test]
    public function it_maps_with_array(): void
    {
        $mapper = new ClosureRowMapper(fn (array $item) => [
            'id'   => $item['id'],
            'name' => strtoupper($item['name']),
        ]);

        $result = $mapper->map(['id' => 1, 'name' => 'john']);

        $this->assertSame(['id' => 1, 'name' => 'JOHN'], $result);
    }

    #[Test]
    public function it_maps_with_object(): void
    {
        $mapper = new ClosureRowMapper(function (object $item) {
            return [
                'id'   => $item->id,
                'name' => ucfirst($item->name),
            ];
        });

        $result = $mapper->map((object) ['id' => 2, 'name' => 'doe']);

        $this->assertSame(['id' => 2, 'name' => 'Doe'], $result);
    }
}
