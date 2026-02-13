<?php

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
use Pentiminax\UX\DataTables\ApiPlatform\PropertyNameHumanizer;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\TypeInfo\Type;

final class TestPropertyInfoExtractor implements PropertyInfoExtractorInterface
{
    /**
     * @var \Closure(string, string, array): ?Type
     */
    private \Closure $typeResolver;

    public function __construct()
    {
        $this->typeResolver = static fn (): ?Type => null;
    }

    /**
     * @param \Closure(string, string, array): ?Type $typeResolver
     */
    public function setTypeResolver(\Closure $typeResolver): void
    {
        $this->typeResolver = $typeResolver;
    }

    public function getType(string $class, string $property, array $context = []): ?Type
    {
        return ($this->typeResolver)($class, $property, $context);
    }

    public function getTypes(string $class, string $property, array $context = []): ?array
    {
        $type = $this->getType($class, $property, $context);

        return null === $type ? null : [$type];
    }

    public function getShortDescription(string $class, string $property, array $context = []): ?string
    {
        return null;
    }

    public function getLongDescription(string $class, string $property, array $context = []): ?string
    {
        return null;
    }

    public function isReadable(string $class, string $property, array $context = []): ?bool
    {
        return null;
    }

    public function isWritable(string $class, string $property, array $context = []): ?bool
    {
        return null;
    }

    public function getProperties(string $class, array $context = []): ?array
    {
        return null;
    }
}

class ColumnAutoDetectorTest extends TestCase
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

        $this->resourceMetadataFactory = $this->createMock(ResourceMetadataCollectionFactoryInterface::class);
        $this->propertyNameFactory     = $this->createMock(PropertyNameCollectionFactoryInterface::class);
        $this->propertyMetadataFactory = $this->createMock(PropertyMetadataFactoryInterface::class);
        $this->propertyInfoExtractor   = new TestPropertyInfoExtractor();
    }

    public function testSupportsReturnsTrueForApiResource(): void
    {
        $this->resourceMetadataFactory
            ->method('create')
            ->willReturn(new ResourceMetadataCollection('Foo', [new ApiResource()]));

        $detector = $this->createDetector();

        $this->assertTrue($detector->supports('App\Entity\Foo'));
    }

    public function testSupportsReturnsFalseForNonApiResource(): void
    {
        $this->resourceMetadataFactory
            ->method('create')
            ->willReturn(new ResourceMetadataCollection('Foo', []));

        $detector = $this->createDetector();

        $this->assertFalse($detector->supports('App\Entity\Foo'));
    }

    public function testSupportsReturnsFalseOnException(): void
    {
        $this->resourceMetadataFactory
            ->method('create')
            ->willThrowException(new \RuntimeException('Not found'));

        $detector = $this->createDetector();

        $this->assertFalse($detector->supports('App\Entity\Unknown'));
    }

    public function testDetectColumnsGeneratesCorrectTypes(): void
    {
        $this->propertyNameFactory
            ->method('create')
            ->willReturn(new PropertyNameCollection(['id', 'name', 'price', 'active']));

        $this->propertyMetadataFactory
            ->method('create')
            ->willReturnCallback(function (string $class, string $property): ApiProperty {
                return match ($property) {
                    'id'     => (new ApiProperty())->withIdentifier(true)->withReadable(true),
                    'name'   => (new ApiProperty())->withReadable(true),
                    'price'  => (new ApiProperty())->withReadable(true),
                    'active' => (new ApiProperty())->withReadable(true),
                };
            });

        $this->propertyInfoExtractor->setTypeResolver(
            static function (string $class, string $property): ?Type {
                return match ($property) {
                    'id'     => Type::int(),
                    'name'   => Type::string(),
                    'price'  => Type::float(),
                    'active' => Type::bool(),
                    default  => null,
                };
            }
        );

        $detector = $this->createDetector();
        $columns  = $detector->detectColumns('App\Entity\Product');

        $this->assertCount(4, $columns);

        // id — NumberColumn, hidden (identifier)
        $this->assertInstanceOf(NumberColumn::class, $columns[0]);
        $this->assertSame('id', $columns[0]->getName());
        $this->assertFalse($columns[0]->jsonSerialize()['visible']);

        // name — TextColumn
        $this->assertInstanceOf(TextColumn::class, $columns[1]);
        $this->assertSame('name', $columns[1]->getName());

        // price — NumberColumn
        $this->assertInstanceOf(NumberColumn::class, $columns[2]);

        // active — BooleanColumn
        $this->assertInstanceOf(BooleanColumn::class, $columns[3]);
    }

    public function testWriteOnlyPropertiesAreExcluded(): void
    {
        $this->propertyNameFactory
            ->method('create')
            ->willReturn(new PropertyNameCollection(['name', 'password']));

        $this->propertyMetadataFactory
            ->method('create')
            ->willReturnCallback(function (string $class, string $property): ApiProperty {
                return match ($property) {
                    'name'     => (new ApiProperty())->withReadable(true),
                    'password' => (new ApiProperty())->withReadable(false),
                };
            });

        $this->propertyInfoExtractor->setTypeResolver(static fn (): ?Type => Type::string());

        $detector = $this->createDetector();
        $columns  = $detector->detectColumns('App\Entity\User');

        $this->assertCount(1, $columns);
        $this->assertSame('name', $columns[0]->getName());
    }

    public function testLabelsAreHumanized(): void
    {
        $this->propertyNameFactory
            ->method('create')
            ->willReturn(new PropertyNameCollection(['createdAt', 'first_name']));

        $this->propertyMetadataFactory
            ->method('create')
            ->willReturn((new ApiProperty())->withReadable(true));

        $this->propertyInfoExtractor->setTypeResolver(static fn (): ?Type => Type::string());

        $detector = $this->createDetector();
        $columns  = $detector->detectColumns('App\Entity\User');

        $this->assertSame('Created At', $columns[0]->jsonSerialize()['title']);
        $this->assertSame('First Name', $columns[1]->jsonSerialize()['title']);
    }

    public function testHumanizeConvertsIdToUppercase(): void
    {
        $humanizer = new PropertyNameHumanizer();

        $this->assertSame('ID', $humanizer->humanize('id'));
        $this->assertSame('User ID', $humanizer->humanize('userId'));
    }

    public function testDetectColumnsPassesSerializationGroups(): void
    {
        $groups = ['product:list'];

        $this->propertyNameFactory
            ->expects($this->once())
            ->method('create')
            ->with('App\Entity\Product', ['serializer_groups' => $groups])
            ->willReturn(new PropertyNameCollection([]));

        $detector = $this->createDetector();
        $detector->detectColumns('App\Entity\Product', $groups);
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
