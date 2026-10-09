<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit;

use Pentiminax\UX\DataTables\Column\Rendering\PropertyReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PropertyReader::class)]
final class PropertyReaderTest extends TestCase
{
    public static function readablePaths(): iterable
    {
        $entity    = new PropertyReaderStub(name: 'Bob', active: true, role: 'admin');
        $accessors = new PropertyReaderAccessorStub();
        $stock     = new PropertyReaderStockStub(new PropertyReaderStringableProductStub('REF-001', 'Widget'));

        yield 'array path' => [['user' => ['name' => 'Alice']], 'user.name', 'Alice'];
        yield 'getter' => [$entity, 'name', 'Bob'];
        yield 'is prefix' => [$entity, 'active', true];
        yield 'has prefix' => [$entity, 'role', true];
        yield 'direct callable' => [$entity, 'score', 42];
        yield 'public property' => [new PropertyReaderFieldStub(), 'field', 'public_value'];
        yield 'snake case accessor' => [$accessors, 'first_name', 'snake_value'];
        yield 'kebab case accessor' => [$accessors, 'last-name', 'kebab_value'];
        yield 'stringable return value' => [$accessors, 'label', 'stringified'];
        yield 'nested path' => [$stock, 'product.ref', 'REF-001'];
        yield 'nested path on stringable' => [$stock, 'product.name', 'Widget'];
        yield 'stringable leaf entity' => [$stock, 'product', 'Widget'];
    }

    #[Test]
    #[DataProvider('readablePaths')]
    public function it_reads_values_through_every_supported_access_strategy(mixed $subject, string $path, mixed $expected): void
    {
        $this->assertSame($expected, PropertyReader::readPath($subject, $path));
    }

    #[Test]
    public function it_returns_null_for_private_property(): void
    {
        $object = new PropertyReaderFieldStub();

        $this->assertNull(PropertyReader::readObjectValue($object, 'secret'));
        $this->assertNull(PropertyReader::readPath($object, 'secret'));
        $this->assertSame('public_value', PropertyReader::readPath($object, 'field'));
    }

    #[Test]
    public function it_returns_null_for_unknown_property(): void
    {
        $object = new PropertyReaderStub(name: 'Bob', active: true, role: 'admin');

        $this->assertNull(PropertyReader::readObjectValue($object, 'nonexistent'));
        $this->assertNull(PropertyReader::readPath($object, 'nonexistent'));
    }

    #[Test]
    public function it_returns_null_for_missing_array_key(): void
    {
        $this->assertNull(PropertyReader::readPath(['a' => 1], 'b'));
    }

    #[Test]
    public function it_returns_null_for_empty_path(): void
    {
        $this->assertNull(PropertyReader::readPath(['foo' => 'bar'], ''));
    }

    #[Test]
    public function it_returns_null_for_scalar_segment(): void
    {
        $this->assertNull(PropertyReader::readPath('scalar', 'foo'));
    }

    #[Test]
    public function it_reads_a_public_property_when_has_accessor_requires_arguments(): void
    {
        $account = new PropertyReaderRequiredHasStub(role: 'admin');

        $this->assertSame('admin', PropertyReader::readPath($account, 'role'));
        $this->assertSame('admin', PropertyReader::readObjectValue($account, 'role'));
    }

    #[Test]
    public function it_skips_collection_predicates_that_require_an_argument(): void
    {
        $post = new PropertyReaderCollectionStub();

        $this->assertNull(PropertyReader::readPath($post, 'tag'));
        $this->assertSame([], PropertyReader::readPath($post, 'tags'));
    }

    #[Test]
    public function it_reads_a_public_property_instead_of_dispatching_call(): void
    {
        $object = new PropertyReaderMagicStub();

        $this->assertSame('admin', PropertyReader::readPath($object, 'role'));
        $this->assertSame([], $object->called);
    }

    #[Test]
    public function it_keeps_a_null_return_from_a_zero_arg_getter(): void
    {
        $object = new PropertyReaderNullableGetterStub();

        $this->assertNull(PropertyReader::readPath($object, 'nickname'));
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

final class PropertyReaderFieldStub
{
    public string $field = 'public_value';

    private string $secret = 'hidden';
}

final class PropertyReaderAccessorStub
{
    public function getFirstName(): string
    {
        return 'snake_value';
    }

    public function getLastName(): string
    {
        return 'kebab_value';
    }

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

final class PropertyReaderRequiredHasStub
{
    public function __construct(public string $role)
    {
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }
}

final class PropertyReaderCollectionStub
{
    /** @var list<object> */
    private array $tags = [];

    public function hasTag(object $tag): bool
    {
        return \in_array($tag, $this->tags, true);
    }

    /**
     * @return list<object>
     */
    public function getTags(): array
    {
        return $this->tags;
    }
}

final class PropertyReaderMagicStub
{
    /** @var list<string> */
    public array $called = [];

    public string $role = 'admin';

    public function __call(string $name, array $arguments): mixed
    {
        $this->called[] = $name;

        return 'via_call';
    }
}

final class PropertyReaderNullableGetterStub
{
    public string $nickname = 'should-not-be-read';

    public function getNickname(): ?string
    {
        return null;
    }
}
