<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\ApiPlatform;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Property\PropertyNameCollection;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use Pentiminax\UX\DataTables\ApiPlatform\ApiPlatformPropertyTypeMapper;
use Pentiminax\UX\DataTables\ApiPlatform\ColumnAutoDetector;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\PropertyNameHumanizer;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Tests\Fixtures\ApiPlatform\TestPropertyInfoExtractor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\TypeInfo\Type;

/**
 * @internal
 */
#[CoversClass(ColumnAutoDetector::class)]
final class ColumnAutoDetectorTest extends TestCase
{
    private ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory;
    private PropertyNameCollectionFactoryInterface $propertyNameFactory;
    private PropertyMetadataFactoryInterface $propertyMetadataFactory;
    private TestPropertyInfoExtractor $propertyInfoExtractor;

    protected function setUp(): void
    {
        if (!interface_exists(ResourceMetadataCollectionFactoryInterface::class)) {
            $this->markTestSkipped('API Platform is not installed.');
        }

        $this->resourceMetadataFactory = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
        $this->propertyNameFactory     = $this->createMock(PropertyNameCollectionFactoryInterface::class);
        $this->propertyMetadataFactory = $this->createStub(PropertyMetadataFactoryInterface::class);
        $this->propertyInfoExtractor   = new TestPropertyInfoExtractor();
    }

    #[Test]
    #[TestWith([true])]
    #[TestWith([false])]
    public function it_supports_only_classes_exposed_as_api_resources(bool $isApiResource): void
    {
        $this->resourceMetadataFactory
            ->method('create')
            ->willReturn(new ResourceMetadataCollection('Foo', $isApiResource ? [new ApiResource()] : []));

        $this->assertSame($isApiResource, $this->createDetector()->supports('App\Entity\Foo'));
    }

    #[Test]
    public function it_does_not_support_on_exception(): void
    {
        $this->resourceMetadataFactory
            ->method('create')
            ->willThrowException(new \RuntimeException('Not found'));

        $this->assertFalse($this->createDetector()->supports('App\Entity\Unknown'));
    }

    #[Test]
    public function it_generates_correct_column_types(): void
    {
        $this->givenProperties(['id', 'name', 'price', 'active']);

        $this->propertyMetadataFactory
            ->method('create')
            ->willReturnCallback(static fn (string $class, string $property): ApiProperty => 'id' === $property
                ? (new ApiProperty())->withIdentifier(true)->withReadable(true)
                : (new ApiProperty())->withReadable(true));

        $this->propertyInfoExtractor->setTypeResolver(
            static fn (string $class, string $property): ?Type => match ($property) {
                'id'     => Type::int(),
                'name'   => Type::string(),
                'price'  => Type::float(),
                'active' => Type::bool(),
                default  => null,
            }
        );

        $columns = $this->createDetector()->detectColumns('App\Entity\Product');

        $this->assertSame(
            [NumberColumn::class, TextColumn::class, NumberColumn::class, BooleanColumn::class],
            array_map(static fn (ColumnInterface $column): string => $column::class, $columns)
        );
        $this->assertSame([
            [
                'data'       => 'id',
                'name'       => 'id',
                'orderable'  => true,
                'searchable' => true,
                'title'      => 'ID',
                'type'       => 'num',
                'visible'    => false,
                'field'      => 'id',
            ],
            [
                'data'       => 'name',
                'name'       => 'name',
                'orderable'  => true,
                'searchable' => true,
                'title'      => 'Name',
                'type'       => 'string',
                'visible'    => true,
                'field'      => 'name',
            ],
            [
                'data'       => 'price',
                'name'       => 'price',
                'orderable'  => true,
                'searchable' => true,
                'title'      => 'Price',
                'type'       => 'num',
                'visible'    => true,
                'field'      => 'price',
            ],
            [
                'data'       => 'active',
                'name'       => 'active',
                'orderable'  => true,
                'searchable' => true,
                'title'      => 'Active',
                'type'       => 'num',
                'visible'    => true,
                'field'      => 'active',
            ],
        ], array_map(static fn (ColumnInterface $column): array => $column->jsonSerialize(), $columns));
    }

    #[Test]
    public function it_excludes_write_only_properties(): void
    {
        $this->givenProperties(['name', 'password']);

        $this->propertyMetadataFactory
            ->method('create')
            ->willReturnCallback(static fn (string $class, string $property): ApiProperty => (new ApiProperty())
                ->withReadable('password' !== $property));

        $this->propertyInfoExtractor->setTypeResolver(static fn (): ?Type => Type::string());

        $columns = $this->createDetector()->detectColumns('App\Entity\User');

        $this->assertSame(['name'], array_map(
            static fn (ColumnInterface $column): string => $column->getName(),
            $columns
        ));
    }

    #[Test]
    public function it_humanizes_labels(): void
    {
        $this->givenProperties(['createdAt', 'first_name', 'id', 'userId']);

        $this->propertyMetadataFactory
            ->method('create')
            ->willReturn((new ApiProperty())->withReadable(true));

        $this->propertyInfoExtractor->setTypeResolver(static fn (): ?Type => Type::string());

        $columns = $this->createDetector()->detectColumns('App\Entity\User');

        $this->assertSame(['Created At', 'First Name', 'ID', 'User ID'], array_map(
            static fn (ColumnInterface $column): string => $column->jsonSerialize()['title'],
            $columns
        ));
    }

    #[Test]
    public function it_passes_serialization_groups(): void
    {
        $groups = ['product:list'];

        $this->propertyNameFactory
            ->expects($this->once())
            ->method('create')
            ->with('App\Entity\Product', ['serializer_groups' => $groups])
            ->willReturn(new PropertyNameCollection([]));

        $this->createDetector()->detectColumns('App\Entity\Product', $groups);
    }

    /**
     * @param string[] $properties
     */
    private function givenProperties(array $properties): void
    {
        $this->propertyNameFactory
            ->method('create')
            ->willReturn(new PropertyNameCollection($properties));
    }

    private function createDetector(): ColumnAutoDetector
    {
        return new ColumnAutoDetector(
            $this->resourceMetadataFactory,
            $this->propertyNameFactory,
            $this->propertyMetadataFactory,
            $this->propertyInfoExtractor,
            new ApiPlatformPropertyTypeMapper(),
            new PropertyNameHumanizer(),
        );
    }
}
