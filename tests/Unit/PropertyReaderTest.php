<?php

namespace Pentiminax\UX\DataTables\Tests\Unit;

use Pentiminax\UX\DataTables\Util\PropertyReader;
use PHPUnit\Framework\TestCase;

final class PropertyReaderTest extends TestCase
{
    public function testReadPathReturnsNullForEmptyPath(): void
    {
        $this->assertNull(PropertyReader::readPath(['foo' => 'bar'], ''));
    }

    public function testReadPathFromArray(): void
    {
        $data = ['user' => ['name' => 'Alice']];

        $this->assertSame('Alice', PropertyReader::readPath($data, 'user.name'));
    }

    public function testReadPathFromArrayReturnsNullForMissingKey(): void
    {
        $this->assertNull(PropertyReader::readPath(['a' => 1], 'b'));
    }

    public function testReadPathFromObjectViaGetter(): void
    {
        $object = new PropertyReaderStub(name: 'Bob', active: true, role: 'admin');

        $this->assertSame('Bob', PropertyReader::readPath($object, 'name'));
    }

    public function testReadPathFromObjectViaIsPrefix(): void
    {
        $object = new PropertyReaderStub(name: 'Bob', active: true, role: 'admin');

        $this->assertTrue(PropertyReader::readPath($object, 'active'));
    }

    public function testReadPathFromObjectViaHasPrefix(): void
    {
        $object = new PropertyReaderStub(name: 'Bob', active: true, role: 'admin');

        $this->assertTrue(PropertyReader::readPath($object, 'role'));
    }

    public function testReadPathFromObjectViaDirectCallable(): void
    {
        $object = new PropertyReaderStub(name: 'Bob', active: true, role: 'admin');

        $this->assertSame(42, PropertyReader::readPath($object, 'score'));
    }

    public function testReadPathFromPublicProperty(): void
    {
        $object = new PropertyReaderPublicFieldStub();

        $this->assertSame('public_value', PropertyReader::readPath($object, 'field'));
    }

    public function testReadPathReturnsNullForUnknownProperty(): void
    {
        $object = new PropertyReaderStub(name: 'Bob', active: true, role: 'admin');

        $this->assertNull(PropertyReader::readPath($object, 'nonexistent'));
    }

    public function testReadPathHandlesSnakeCaseAccessor(): void
    {
        $object = new PropertyReaderSnakeCaseStub();

        $this->assertSame('snake_value', PropertyReader::readPath($object, 'first_name'));
    }

    public function testReadPathHandlesKebabCaseAccessor(): void
    {
        $object = new PropertyReaderKebabCaseStub();

        $this->assertSame('kebab_value', PropertyReader::readPath($object, 'last-name'));
    }

    public function testReadPathHandlesStringableReturnValue(): void
    {
        $object = new PropertyReaderStringableStub();

        $this->assertSame('stringified', PropertyReader::readPath($object, 'label'));
    }

    public function testReadPathTraversesDotNotationOnNestedObjects(): void
    {
        $address = new PropertyReaderAddressStub('Paris');
        $user    = new PropertyReaderUserStub($address);

        $this->assertSame('Paris', PropertyReader::readPath($user, 'address.city'));
    }

    public function testReadPathReturnsNullForScalarSegment(): void
    {
        $this->assertNull(PropertyReader::readPath('scalar', 'foo'));
    }

    public function testReadObjectValueReturnsNullForPrivateProperty(): void
    {
        $object = new PropertyReaderPrivateFieldStub();

        $this->assertNull(PropertyReader::readObjectValue($object, 'secret'));
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
