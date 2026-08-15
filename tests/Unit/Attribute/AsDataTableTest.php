<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Attribute;

use Doctrine\ORM\EntityManagerInterface;
use Pentiminax\UX\DataTables\ApiPlatform\ApiResourceCollectionUrlResolverInterface;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\DataProvider\ArrayDataProvider;
use Pentiminax\UX\DataTables\DataProvider\AutoDataProviderFactory;
use Pentiminax\UX\DataTables\DataProvider\DataProviderResolver;
use Pentiminax\UX\DataTables\DataProvider\DoctrineDataProvider;
use Pentiminax\UX\DataTables\Mercure\MercureConfig;
use Pentiminax\UX\DataTables\Mercure\MercureConfigResolverInterface;
use Pentiminax\UX\DataTables\Mercure\MercureHubUrlResolverInterface;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Runtime\DataTableInfrastructure;
use Pentiminax\UX\DataTables\Runtime\DataTableRuntimeFactory;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\TestDataTableWithAttribute;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\TestDataTableWithData;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\TestDataTableWithManualAjax;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\TestDataTableWithManualMercure;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\TestDataTableWithManualOverride;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\TestDataTableWithMercureAndData;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\TestDataTableWithMercureAndManualAjax;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\TestDataTableWithMercureAttribute;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\TestDataTableWithMercureTopicsAttribute;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\TestDataTableWithoutAttribute;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\TestDataTableWithServerSide;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AsDataTable::class)]
final class AsDataTableTest extends TestCase
{
    #[Test]
    public function it_auto_configures_and_caches_the_data_provider(): void
    {
        $table = new TestDataTableWithAttribute();
        $em    = $this->createMock(EntityManagerInterface::class);
        $table->setDataTableInfrastructure(DataTableInfrastructure::createDefault(
            runtimeFactory: new DataTableRuntimeFactory(
                dataProviderResolver: new DataProviderResolver(new AutoDataProviderFactory($em))
            )
        ));

        $provider = $table->getDataProvider();

        $this->assertInstanceOf(DoctrineDataProvider::class, $provider);
        $this->assertSame($provider, $table->getDataProvider());
    }

    #[Test]
    public function it_manual_override_takes_precedence(): void
    {
        $table = new TestDataTableWithManualOverride();

        $provider = $table->getDataProvider();

        $this->assertInstanceOf(ArrayDataProvider::class, $provider);
    }

    #[Test]
    public function it_returns_null_without_attribute(): void
    {
        $table = new TestDataTableWithoutAttribute();

        $this->assertNull($table->getDataProvider());
    }

    /**
     * @param class-string<AbstractDataTable> $tableClass
     */
    #[Test]
    #[DataProvider('provideApiPlatformTables')]
    public function it_configures_ajax_for_api_resource(string $tableClass): void
    {
        $resolver = $this->createMock(ApiResourceCollectionUrlResolverInterface::class);
        $resolver
            ->expects($this->once())
            ->method('resolveCollectionUrl')
            ->with(\stdClass::class)
            ->willReturn('/api/books');

        $table = new $tableClass(apiResourceCollectionUrlResolver: $resolver);

        $table->prepareForRendering();

        $this->assertSame([
            'type' => 'GET',
            'url'  => '/api/books',
        ], $table->getDataTable()->getOption('ajax'));

        $this->assertTrue($table->getDataTable()->getOption('apiPlatform'));
    }

    public static function provideApiPlatformTables(): iterable
    {
        yield 'client side' => [TestDataTableWithAttribute::class];

        yield 'server side' => [TestDataTableWithServerSide::class];
    }

    #[Test]
    public function it_does_nothing_when_ajax_already_configured(): void
    {
        $resolver = $this->createMock(ApiResourceCollectionUrlResolverInterface::class);
        $resolver->expects($this->never())->method('resolveCollectionUrl');

        $table = new TestDataTableWithManualAjax(apiResourceCollectionUrlResolver: $resolver);

        $table->prepareForRendering();

        $this->assertSame([
            'type' => 'GET',
            'url'  => '/custom-endpoint',
        ], $table->getDataTable()->getOption('ajax'));

        $this->assertFalse($table->getDataTable()->getOption('apiPlatform') ?? false);
    }

    #[Test]
    public function it_does_nothing_when_data_already_configured(): void
    {
        $resolver = $this->createMock(ApiResourceCollectionUrlResolverInterface::class);
        $resolver->expects($this->never())->method('resolveCollectionUrl');

        $table = new TestDataTableWithData(apiResourceCollectionUrlResolver: $resolver);

        $table->prepareForRendering();

        $this->assertNull($table->getDataTable()->getOption('ajax'));
    }

    #[Test]
    public function it_does_nothing_without_attribute(): void
    {
        $resolver = $this->createMock(ApiResourceCollectionUrlResolverInterface::class);
        $resolver->expects($this->never())->method('resolveCollectionUrl');

        $table = new TestDataTableWithoutAttribute(apiResourceCollectionUrlResolver: $resolver);

        $table->prepareForRendering();

        $this->assertNull($table->getDataTable()->getOption('ajax'));
    }

    #[Test]
    public function it_does_nothing_without_resolver(): void
    {
        $table = new TestDataTableWithAttribute();

        $table->prepareForRendering();

        $this->assertNull($table->getDataTable()->getOption('ajax'));
    }

    /**
     * @param class-string<AbstractDataTable> $tableClass
     * @param string[]                        $topics
     */
    #[Test]
    #[DataProvider('provideAutoConfiguredMercureTables')]
    public function it_auto_configures_mercure_for_attribute(string $tableClass, array $topics, string $ajaxUrl): void
    {
        $resolver = $this->createMock(MercureConfigResolverInterface::class);
        $resolver
            ->expects($this->once())
            ->method('resolveMercureConfig')
            ->with(\stdClass::class)
            ->willReturn(
                (new MercureConfig(topics: $topics))
                    ->withHubUrl('http://localhost/.well-known/mercure')
            );

        $table = new $tableClass(mercureConfigResolver: $resolver);

        $table->prepareForRendering();

        $this->assertSame([
            'type' => 'GET',
            'url'  => $ajaxUrl,
        ], $table->getDataTable()->getOption('ajax'));

        $this->assertSame([
            'hubUrl' => 'http://localhost/.well-known/mercure',
            'topics' => $topics,
        ], $table->getDataTable()->getOptions()['mercure']);
    }

    public static function provideAutoConfiguredMercureTables(): iterable
    {
        yield 'attribute only' => [
            TestDataTableWithMercureAttribute::class,
            ['/api/books/{id}'],
            '/api/books',
        ];

        yield 'manual ajax' => [
            TestDataTableWithMercureAndManualAjax::class,
            ['/api/books/{id}', '/api/authors/{id}'],
            '/custom-endpoint',
        ];
    }

    #[Test]
    public function it_configures_mercure_from_attribute_topics(): void
    {
        $resolver = $this->createMock(MercureConfigResolverInterface::class);
        $resolver->expects($this->never())->method('resolveMercureConfig');

        $hubUrlResolver = $this->createMock(MercureHubUrlResolverInterface::class);
        $hubUrlResolver->method('resolveHubUrl')->willReturn('/.well-known/mercure');

        $table = new TestDataTableWithMercureTopicsAttribute(
            mercureConfigResolver: $resolver,
            mercureHubUrlResolver: $hubUrlResolver,
        );

        $table->prepareForRendering();

        $this->assertSame([
            'hubUrl' => '/.well-known/mercure',
            'topics' => ['https://example.com/books'],
        ], $table->getDataTable()->getOptions()['mercure']);
    }

    #[Test]
    public function it_does_not_auto_configure_mercure_for_static_data(): void
    {
        $resolver = $this->createMock(MercureConfigResolverInterface::class);
        $resolver->expects($this->never())->method('resolveMercureConfig');

        $table = new TestDataTableWithMercureAndData(mercureConfigResolver: $resolver);

        $table->prepareForRendering();

        $this->assertArrayNotHasKey('mercure', $table->getDataTable()->getOptions());
    }

    #[Test]
    public function it_keeps_manual_mercure_configuration(): void
    {
        $resolver = $this->createMock(MercureConfigResolverInterface::class);
        $resolver->expects($this->never())->method('resolveMercureConfig');

        $hubUrlResolver = $this->createMock(MercureHubUrlResolverInterface::class);
        $hubUrlResolver->method('resolveHubUrl')->willReturn('/.well-known/mercure');

        $table = new TestDataTableWithManualMercure(
            mercureConfigResolver: $resolver,
            mercureHubUrlResolver: $hubUrlResolver,
        );

        $table->prepareForRendering();

        $this->assertSame([
            'hubUrl' => '/.well-known/mercure',
            'topics' => ['manual/topic'],
        ], $table->getDataTable()->getOptions()['mercure']);
    }
}
