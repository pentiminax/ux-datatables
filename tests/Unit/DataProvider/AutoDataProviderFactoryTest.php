<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\DataProvider;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\DataProvider\ApiPlatformCollectionProvider;
use Pentiminax\UX\DataTables\DataProvider\ApiPlatformCollectionProviderFactory;
use Pentiminax\UX\DataTables\DataProvider\AutoDataProviderFactory;
use Pentiminax\UX\DataTables\DataProvider\DoctrineDataProvider;
use Pentiminax\UX\DataTables\RowMapper\DefaultRowMapper;
use Pentiminax\UX\DataTables\Tests\Support\BuildsApiPlatformProviderFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AutoDataProviderFactory::class)]
final class AutoDataProviderFactoryTest extends TestCase
{
    use BuildsApiPlatformProviderFactory;

    #[Test]
    public function it_auto_configures_a_doctrine_provider_when_attribute_and_entity_manager_are_available(): void
    {
        $provider = $this->create(
            asDataTable: new AsDataTable(entityClass: \stdClass::class),
            em: $this->createStub(EntityManagerInterface::class),
        );

        $this->assertInstanceOf(DoctrineDataProvider::class, $provider);
    }

    #[Test]
    public function it_queries_the_entity_class_when_the_data_class_is_a_plain_dto(): void
    {
        $provider = $this->create(
            asDataTable: new AsDataTable(dataClass: PlainRowFixture::class, entityClass: \stdClass::class),
            em: $this->entityManagerMapping(\stdClass::class),
        );

        $this->assertInstanceOf(DoctrineDataProvider::class, $provider);
    }

    #[Test]
    public function it_throws_when_the_entity_class_is_not_mapped(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The entity class "Pentiminax\UX\DataTables\Tests\Unit\DataProvider\PlainRowFixture" declared on #[AsDataTable] is not mapped by Doctrine');

        $this->create(
            asDataTable: new AsDataTable(dataClass: PlainRowFixture::class),
            em: $this->entityManagerMapping(\stdClass::class),
        );
    }

    #[Test]
    public function it_returns_null_without_attribute(): void
    {
        $this->assertNull($this->create());
    }

    #[Test]
    public function it_throws_when_auto_configuration_requires_an_entity_manager(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('EntityManagerInterface is required to auto-configure a DoctrineDataProvider');

        $this->create(asDataTable: new AsDataTable(entityClass: \stdClass::class));
    }

    #[Test]
    public function it_auto_configures_an_api_platform_provider_when_the_integration_is_requested(): void
    {
        $provider = $this->create(
            asDataTable: new AsDataTable(entityClass: \stdClass::class, apiPlatform: true),
            apiPlatformProviderFactory: $this->buildApiPlatformProviderFactory(),
            apiPlatform: true,
        );

        $this->assertInstanceOf(ApiPlatformCollectionProvider::class, $provider);
    }

    #[Test]
    public function it_forwards_the_page_projector_to_the_api_platform_provider(): void
    {
        $projector = static fn (array $items): array => $items;

        $provider = $this->create(
            asDataTable: new AsDataTable(entityClass: \stdClass::class, apiPlatform: true),
            apiPlatformProviderFactory: $this->buildApiPlatformProviderFactory(),
            apiPlatform: true,
            pageProjector: $projector,
        );

        $this->assertInstanceOf(ApiPlatformCollectionProvider::class, $provider);
        $this->assertSame($projector, (new \ReflectionProperty($provider, 'pageProjector'))->getValue($provider));
    }

    /**
     * The RenderingPreparer leaves a table whose entity exposes no collection operation on its
     * Doctrine wiring, so the provider must fall back the same way instead of raising later.
     */
    #[Test]
    public function it_falls_back_to_doctrine_when_the_entity_exposes_no_collection_operation(): void
    {
        $provider = $this->create(
            asDataTable: new AsDataTable(entityClass: \stdClass::class, apiPlatform: true),
            em: $this->createStub(EntityManagerInterface::class),
            apiPlatformProviderFactory: $this->buildApiPlatformProviderFactory(withCollectionOperation: false),
            apiPlatform: true,
        );

        $this->assertInstanceOf(DoctrineDataProvider::class, $provider);
    }

    /**
     * @param class-string $mappedClass
     */
    private function entityManagerMapping(string $mappedClass): EntityManagerInterface
    {
        $metadataFactory = $this->createStub(ClassMetadataFactory::class);
        $metadataFactory
            ->method('isTransient')
            ->willReturnCallback(static fn (string $class): bool => $class !== $mappedClass);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);

        return $em;
    }

    private function create(
        ?AsDataTable $asDataTable = null,
        ?EntityManagerInterface $em = null,
        ?ApiPlatformCollectionProviderFactory $apiPlatformProviderFactory = null,
        bool $apiPlatform = false,
        ?\Closure $pageProjector = null,
    ): ?DataProviderInterface {
        return (new AutoDataProviderFactory($em, $apiPlatformProviderFactory))->create(
            asDataTable: $asDataTable,
            rowMapper: new DefaultRowMapper([]),
            configureQueryBuilder: static fn ($qb, $request) => $qb,
            pageProjector: $pageProjector,
            apiPlatform: $apiPlatform,
        );
    }
}

final class PlainRowFixture
{
    public string $label = '';
}
