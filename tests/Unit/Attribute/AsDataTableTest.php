<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Attribute;

use Doctrine\ORM\EntityManagerInterface;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Contracts\ApiResourceCollectionUrlResolverInterface;
use Pentiminax\UX\DataTables\Contracts\MercureConfigResolverInterface;
use Pentiminax\UX\DataTables\DataProvider\ArrayDataProvider;
use Pentiminax\UX\DataTables\DataProvider\DoctrineDataProvider;
use Pentiminax\UX\DataTables\Mercure\MercureConfig;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\TestDataTableWithAttribute;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\TestDataTableWithBooleanColumn;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\TestDataTableWithData;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\TestDataTableWithManualAjax;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\TestDataTableWithManualMercure;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\TestDataTableWithManualOverride;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\TestDataTableWithMercureAndData;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\TestDataTableWithMercureAndManualAjax;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\TestDataTableWithMercureAttribute;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\TestDataTableWithoutAttribute;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\TestDataTableWithServerSide;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\ToggleEntityFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AsDataTable::class)]
final class AsDataTableTest extends TestCase
{
    #[Test]
    public function it_can_be_instantiated(): void
    {
        $attribute = new AsDataTable(entityClass: \stdClass::class);

        $this->assertSame(\stdClass::class, $attribute->entityClass);
        $this->assertFalse($attribute->mercure);
        $this->assertSame('', $attribute->editModalTemplate);
        $this->assertSame('', $attribute->editModalAdapter);
    }

    #[Test]
    public function it_can_be_applied_to_class(): void
    {
        $reflection = new \ReflectionClass(TestDataTableWithAttribute::class);
        $attributes = $reflection->getAttributes(AsDataTable::class);

        $this->assertCount(1, $attributes);

        $instance = $attributes[0]->newInstance();
        $this->assertInstanceOf(AsDataTable::class, $instance);
        $this->assertSame(\stdClass::class, $instance->entityClass);
        $this->assertFalse($instance->mercure);
        $this->assertSame('', $instance->editModalTemplate);
        $this->assertSame('', $instance->editModalAdapter);
    }

    #[Test]
    public function it_auto_configures_data_provider(): void
    {
        $table = new TestDataTableWithAttribute();
        $em    = $this->createMock(EntityManagerInterface::class);
        $table->setEntityManager($em);

        $provider = $table->getDataProvider();

        $this->assertInstanceOf(DoctrineDataProvider::class, $provider);
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

    #[Test]
    public function it_caches_provider(): void
    {
        $table = new TestDataTableWithAttribute();
        $em    = $this->createMock(EntityManagerInterface::class);
        $table->setEntityManager($em);

        $provider1 = $table->getDataProvider();
        $provider2 = $table->getDataProvider();

        $this->assertSame($provider1, $provider2);
    }

    #[Test]
    public function it_automatically_sets_entity_class_on_boolean_column(): void
    {
        $table  = new TestDataTableWithBooleanColumn();
        $column = $table->getColumnByName('isEmailAuthEnabled');

        $this->assertNotNull($column);
        $this->assertSame(
            ToggleEntityFixture::class,
            $column->jsonSerialize()['customOptions']['entityClass']
        );
    }

    #[Test]
    public function it_configures_ajax_for_api_resource(): void
    {
        $resolver = $this->createMock(ApiResourceCollectionUrlResolverInterface::class);
        $resolver
            ->expects($this->once())
            ->method('resolveCollectionUrl')
            ->with(\stdClass::class)
            ->willReturn('/api/books');

        $table = new TestDataTableWithAttribute(apiResourceCollectionUrlResolver: $resolver);

        $table->prepareForRendering();

        $this->assertSame([
            'type' => 'GET',
            'url'  => '/api/books',
        ], $table->getDataTable()->getOption('ajax'));

        $this->assertTrue($table->getDataTable()->getOption('apiPlatform'));
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
    public function it_configures_ajax_when_server_side_is_enabled(): void
    {
        $resolver = $this->createMock(ApiResourceCollectionUrlResolverInterface::class);
        $resolver
            ->expects($this->once())
            ->method('resolveCollectionUrl')
            ->with(\stdClass::class)
            ->willReturn('/api/books');

        $table = new TestDataTableWithServerSide(apiResourceCollectionUrlResolver: $resolver);

        $table->prepareForRendering();

        $this->assertSame([
            'type' => 'GET',
            'url'  => '/api/books',
        ], $table->getDataTable()->getOption('ajax'));

        $this->assertTrue($table->getDataTable()->getOption('apiPlatform'));
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

    #[Test]
    public function it_auto_configures_mercure_for_attribute(): void
    {
        $resolver = $this->createMock(MercureConfigResolverInterface::class);
        $resolver
            ->expects($this->once())
            ->method('resolveMercureConfig')
            ->with(\stdClass::class)
            ->willReturn(new MercureConfig(
                hubUrl: 'http://localhost/.well-known/mercure',
                topics: ['/api/books/{id}'],
            ));

        $table = new TestDataTableWithMercureAttribute(mercureConfigResolver: $resolver);

        $table->prepareForRendering();

        $this->assertSame([
            'hubUrl' => 'http://localhost/.well-known/mercure',
            'topics' => ['/api/books/{id}'],
        ], $table->getDataTable()->getOptions()['mercure']);
    }

    #[Test]
    public function it_auto_configures_mercure_with_manual_ajax(): void
    {
        $resolver = $this->createMock(MercureConfigResolverInterface::class);
        $resolver
            ->expects($this->once())
            ->method('resolveMercureConfig')
            ->with(\stdClass::class)
            ->willReturn(new MercureConfig(
                hubUrl: 'http://localhost/.well-known/mercure',
                topics: ['/api/books/{id}', '/api/authors/{id}'],
            ));

        $table = new TestDataTableWithMercureAndManualAjax(mercureConfigResolver: $resolver);

        $table->prepareForRendering();

        $this->assertSame([
            'type' => 'GET',
            'url'  => '/custom-endpoint',
        ], $table->getDataTable()->getOption('ajax'));

        $this->assertSame([
            'hubUrl' => 'http://localhost/.well-known/mercure',
            'topics' => ['/api/books/{id}', '/api/authors/{id}'],
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

        $table = new TestDataTableWithManualMercure(mercureConfigResolver: $resolver);

        $table->prepareForRendering();

        $this->assertSame([
            'hubUrl' => '/.well-known/mercure',
            'topics' => ['manual/topic'],
        ], $table->getDataTable()->getOptions()['mercure']);
    }
}
