<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Support;

use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use PHPUnit\Framework\TestCase;

/**
 * Base class for unit tests asserting on serialized DataTable payloads.
 *
 * It deliberately boots no kernel: everything under tests/Unit is a pure unit test.
 * Optional dependencies (Doctrine, Mercure, Form) belong in dedicated Builds* traits
 * so that tests only pull in the doubles they actually need.
 *
 * @internal
 */
abstract class DataTableTestCase extends TestCase
{
    /**
     * Asserts the header keys every column serializes the same way.
     */
    protected function assertColumnHeader(ColumnInterface $column, string $type, string $name, string $title): void
    {
        $data = $column->jsonSerialize();

        $this->assertSame($type, $data['type']);
        $this->assertSame($name, $data['data']);
        $this->assertSame($name, $data['name']);
        $this->assertSame($title, $data['title']);
    }

    /**
     * @param array<string, mixed> $expected the complete expected customOptions payload
     */
    protected function assertCustomOptions(array $expected, ColumnInterface $column): void
    {
        $this->assertSame($expected, $this->customOptions($column));
    }

    protected function assertCustomOption(mixed $expected, string $option, ColumnInterface $column): void
    {
        $options = $this->customOptions($column);

        $this->assertArrayHasKey($option, $options);
        $this->assertSame($expected, $options[$option]);
    }

    protected function assertNoCustomOption(string $option, ColumnInterface $column): void
    {
        $this->assertArrayNotHasKey($option, $this->customOptions($column));
    }

    /**
     * @return array<string, mixed>
     */
    protected function customOptions(ColumnInterface $column): array
    {
        return $column->jsonSerialize()['customOptions'] ?? [];
    }
}
