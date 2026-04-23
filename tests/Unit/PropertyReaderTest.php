<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit;

use Pentiminax\UX\DataTables\Column\Rendering\PropertyReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PropertyReader::class)]
final class PropertyReaderTest extends TestCase
{
    #[Test]
    public function it_returns_null_for_empty_path(): void
    {
        $this->assertNull(PropertyReader::readPath(['foo' => 'bar'], ''));
    }

    #[Test]
    public function it_reads_path_from_array(): void
    {
        $data = ['user' => ['name' => 'Alice']];

        $this->assertSame('Alice', PropertyReader::readPath($data, 'user.name'));
    }

    #[Test]
    public function it_returns_null_for_missing_array_key(): void
    {
        $this->assertNull(PropertyReader::readPath(['a' => 1], 'b'));
    }

    #[Test]
    public function it_reads_from_object_via_getter(): void
    {
        $object = new PropertyReaderStub(name: 'Bob', active: true, role: 'admin');

        $this->assertSame('Bob', PropertyReader::readPath($object, 'name'));
    }

    #[Test]
    public function it_reads_from_object_via_is_prefix(): void
    {
        $object = new PropertyReaderStub(name: 'Bob', active: true, role: 'admin');

        $this->assertTrue(PropertyReader::readPath($object, 'active'));
    }

    #[Test]
    public function it_reads_from_object_via_has_prefix(): void
    {
        $object = new PropertyReaderStub(name: 'Bob', active: true, role: 'admin');

        $this->assertTrue(PropertyReader::readPath($object, 'role'));
    }

    #[Test]
    public function it_reads_from_object_via_direct_callable(): void
    {
        $object = new PropertyReaderStub(name: 'Bob', active: true, role: 'admin');

        $this->assertSame(42, PropertyReader::readPath($object, 'score'));
    }

    #[Test]
    public function it_reads_from_public_property(): void
    {
        $object = new PropertyReaderPublicFieldStub();

        $this->assertSame('public_value', PropertyReader::readPath($object, 'field'));
    }

    #[Test]
    public function it_returns_null_for_unknown_property(): void
    {
        $object = new PropertyReaderStub(name: 'Bob', active: true, role: 'admin');

        $this->assertNull(PropertyReader::readPath($object, 'nonexistent'));
    }

    #[Test]
    public function it_handles_snake_case_accessor(): void
    {
        $object = new PropertyReaderSnakeCaseStub();

        $this->assertSame('snake_value', PropertyReader::readPath($object, 'first_name'));
    }

    #[Test]
    public function it_handles_kebab_case_accessor(): void
    {
        $object = new PropertyReaderKebabCaseStub();

        $this->assertSame('kebab_value', PropertyReader::readPath($object, 'last-name'));
    }

    #[Test]
    public function it_handles_stringable_return_value(): void
    {
        $object = new PropertyReaderStringableStub();

        $this->assertSame('stringified', PropertyReader::readPath($object, 'label'));
    }

    #[Test]
    public function it_traverses_dot_notation_on_nested_objects(): void
    {
        $address = new PropertyReaderAddressStub('Paris');
        $user    = new PropertyReaderUserStub($address);

        $this->assertSame('Paris', PropertyReader::readPath($user, 'address.city'));
    }

    #[Test]
    public function it_returns_null_for_scalar_segment(): void
    {
        $this->assertNull(PropertyReader::readPath('scalar', 'foo'));
    }

    #[Test]
    public function it_returns_null_for_private_property(): void
    {
        $object = new PropertyReaderPrivateFieldStub();

        $this->assertNull(PropertyReader::readObjectValue($object, 'secret'));
    }

    #[Test]
    public function it_traverses_nested_path_when_intermediate_entity_is_stringable(): void
    {
        $product = new PropertyReaderStringableProductStub('REF-001', 'Widget');
        $stock   = new PropertyReaderStockStub($product);

        $this->assertSame('REF-001', PropertyReader::readPath($stock, 'product.ref'));
        $this->assertSame('Widget', PropertyReader::readPath($stock, 'product.name'));
    }

    #[Test]
    public function it_casts_leaf_stringable_entity_to_string(): void
    {
        $product = new PropertyReaderStringableProductStub('REF-001', 'Widget');
        $stock   = new PropertyReaderStockStub($product);

        $this->assertSame('Widget', PropertyReader::readPath($stock, 'product'));
    }
}

final readonly class PropertyReaderStub
{
    public function __construct(
        private string $name,
        private bool $active,
        private string $role,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function hasRole(): bool
    {
        return true;
    }

    public function score(): int
    {
        return 42;
    }
}

final class PropertyReaderPublicFieldStub
{
    public string $field = 'public_value';
}

final class PropertyReaderSnakeCaseStub
{
    public function getFirstName(): string
    {
        return 'snake_value';
    }
}

final class PropertyReaderKebabCaseStub
{
    public function getLastName(): string
    {
        return 'kebab_value';
    }
}

final class PropertyReaderStringableStub
{
    public function getLabel(): \Stringable
    {
        return new class implements \Stringable {
            public function __toString(): string
            {
                return 'stringified';
            }
        };
    }
}

final readonly class PropertyReaderAddressStub
{
    public function __construct(private string $city)
    {
    }

    public function getCity(): string
    {
        return $this->city;
    }
}

final readonly class PropertyReaderUserStub
{
    public function __construct(private PropertyReaderAddressStub $address)
    {
    }

    public function getAddress(): PropertyReaderAddressStub
    {
        return $this->address;
    }
}

final class PropertyReaderPrivateFieldStub
{
    private string $secret = 'hidden';
}

final readonly class PropertyReaderStringableProductStub implements \Stringable
{
    public function __construct(
        private string $ref,
        private string $name,
    ) {
    }

    public function getRef(): string
    {
        return $this->ref;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}

final readonly class PropertyReaderStockStub
{
    public function __construct(private PropertyReaderStringableProductStub $product)
    {
    }

    public function getProduct(): PropertyReaderStringableProductStub
    {
        return $this->product;
    }
}
