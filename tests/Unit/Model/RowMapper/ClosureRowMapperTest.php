<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\Model\RowMapper;

use Pentiminax\UX\DataTables\RowMapper\ClosureRowMapper;
use PHPUnit\Framework\TestCase;

final class ClosureRowMapperTest extends TestCase
{
    public function testMapWithArray(): void
    {
        $mapper = new ClosureRowMapper(fn (array $item) => [
            'id' => $item['id'],
            'name' => strtoupper($item['name']),
        ]);

        $result = $mapper->map(['id' => 1, 'name' => 'john']);

        $this->assertSame(['id' => 1, 'name' => 'JOHN'], $result);
    }

    public function testMapWithObject(): void
    {
        $mapper = new ClosureRowMapper(function (object $item) {
            return [
                'id' => $item->id,
                'name' => ucfirst($item->name),
            ];
        });

        $result = $mapper->map((object)['id' => 2, 'name' => 'doe']);

        $this->assertSame(['id' => 2, 'name' => 'Doe'], $result);
    }
}
