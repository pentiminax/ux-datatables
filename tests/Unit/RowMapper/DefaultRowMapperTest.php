<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\RowMapper;

use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\RowMapper\DefaultRowMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DefaultRowMapper::class)]
final class DefaultRowMapperTest extends TestCase
{
    #[Test]
    public function it_maps_scalar_properties_to_array(): void
    {
        $column = TextColumn::new('name', 'Name');
        $mapper = new DefaultRowMapper([$column]);

        $row = new class {
            public function getName(): string
            {
                return 'Alice';
            }
        };

        $result = $mapper->map($row);

        $this->assertSame(['name' => 'Alice'], $result);
    }

    #[Test]
    public function it_resolves_nested_property_path_via_set_field(): void
    {
        $column = TextColumn::new('client', 'Client')->setField('client.name');
        $mapper = new DefaultRowMapper([$column]);

        $client = new class {
            public function getName(): string
            {
                return 'Acme Corp';
            }
        };

        $row = new class($client) {
            public function __construct(private readonly mixed $clientObj)
            {
            }

            public function getClient(): mixed
            {
                return $this->clientObj;
            }
        };

        $result = $mapper->map($row);

        $this->assertSame(['client' => 'Acme Corp'], $result);
    }

    #[Test]
    public function it_returns_raw_object_when_field_resolves_to_non_stringable_object(): void
    {
        $column = TextColumn::new('client', 'Client');
        $mapper = new DefaultRowMapper([$column]);

        $clientObject = new \stdClass();

        $row = new class($clientObject) {
            public function __construct(private readonly mixed $clientObj)
            {
            }

            public function getClient(): mixed
            {
                return $this->clientObj;
            }
        };

        $result = $mapper->map($row);

        $this->assertInstanceOf(\stdClass::class, $result['client']);
    }

    #[Test]
    public function it_converts_stringable_returned_by_getter_to_string(): void
    {
        $column = TextColumn::new('client', 'Client');
        $mapper = new DefaultRowMapper([$column]);

        $stringableClient = new class implements \Stringable {
            public function __toString(): string
            {
                return 'Stringable Corp';
            }
        };

        $row = new class($stringableClient) {
            public function __construct(private readonly mixed $clientObj)
            {
            }

            public function getClient(): mixed
            {
                return $this->clientObj;
            }
        };

        $result = $mapper->map($row);

        // PropertyReader already converts Stringable to string when reading via getter
        $this->assertSame('Stringable Corp', $result['client']);
    }

    #[Test]
    public function it_returns_raw_datetime_without_formatting(): void
    {
        $column = DateColumn::new('createdAt', 'Created At')->setFormat('d/m/Y');
        $mapper = new DefaultRowMapper([$column]);

        $row = new class {
            public function getCreatedAt(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2024-01-15');
            }
        };

        $result = $mapper->map($row);

        $this->assertInstanceOf(\DateTimeImmutable::class, $result['createdAt']);
    }

    #[Test]
    public function it_passes_array_rows_through_unchanged(): void
    {
        $column = TextColumn::new('name', 'Name');
        $mapper = new DefaultRowMapper([$column]);

        $result = $mapper->map(['name' => 'Bob', 'extra' => 'data']);

        $this->assertSame(['name' => 'Bob', 'extra' => 'data'], $result);
    }
}
