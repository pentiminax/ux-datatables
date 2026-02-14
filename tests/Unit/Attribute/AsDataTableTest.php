<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\Attribute;

use Doctrine\ORM\EntityManagerInterface;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Contracts\ApiResourceCollectionUrlResolverInterface;
use Pentiminax\UX\DataTables\DataProvider\ArrayDataProvider;
use Pentiminax\UX\DataTables\DataProvider\DoctrineDataProvider;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\TestDataTableWithAttribute;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\TestDataTableWithBooleanColumn;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\TestDataTableWithData;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\TestDataTableWithManualAjax;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\TestDataTableWithManualOverride;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\TestDataTableWithoutAttribute;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\TestDataTableWithServerSide;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\ToggleEntityFixture;
use PHPUnit\Framework\TestCase;

class AsDataTableTest extends TestCase
{
    public function testAttributeCanBeInstantiated(): void
    {
        $attribute = new AsDataTable(entityClass: \stdClass::class);

        $this->assertSame(\stdClass::class, $attribute->entityClass);
    }

    public function testAttributeCanBeAppliedToClass(): void
    {
        $reflection = new \ReflectionClass(TestDataTableWithAttribute::class);
        $attributes = $reflection->getAttributes(AsDataTable::class);

        $this->assertCount(1, $attributes);

        $instance = $attributes[0]->newInstance();
        $this->assertInstanceOf(AsDataTable::class, $instance);
        $this->assertSame(\stdClass::class, $instance->entityClass);
    }

    public function testDataProviderAutoConfigured(): void
    {
        $table = new TestDataTableWithAttribute();
        $em    = $this->createMock(EntityManagerInterface::class);
        $table->setEntityManager($em);

        $provider = $table->getDataProvider();

        $this->assertInstanceOf(DoctrineDataProvider::class, $provider);
    }

    public function testManualOverrideTakesPrecedence(): void
    {
        $table = new TestDataTableWithManualOverride();

        $provider = $table->getDataProvider();

        $this->assertInstanceOf(ArrayDataProvider::class, $provider);
    }

    public function testNoAttributeReturnsNull(): void
    {
        $table = new TestDataTableWithoutAttribute();

        $this->assertNull($table->getDataProvider());
    }

    public function testProviderIsCached(): void
    {
        $table = new TestDataTableWithAttribute();
        $em    = $this->createMock(EntityManagerInterface::class);
        $table->setEntityManager($em);

        $provider1 = $table->getDataProvider();
        $provider2 = $table->getDataProvider();

        $this->assertSame($provider1, $provider2);
    }

    public function testBooleanColumnReceivesEntityClassAutomatically(): void
    {
        $table  = new TestDataTableWithBooleanColumn();
        $column = $table->getColumnByName('isEmailAuthEnabled');

        $this->assertNotNull($column);
        $this->assertSame(
            ToggleEntityFixture::class,
            $column->jsonSerialize()['booleanToggleEntityClass']
        );
    }

    public function testPrepareForRenderingConfiguresAjaxForApiResource(): void
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

    public function testPrepareForRenderingDoesNothingWhenAjaxIsAlreadyConfigured(): void
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

    public function testPrepareForRenderingConfiguresAjaxWhenServerSideIsEnabled(): void
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

    public function testPrepareForRenderingDoesNothingWhenDataIsAlreadyConfigured(): void
    {
        $resolver = $this->createMock(ApiResourceCollectionUrlResolverInterface::class);
        $resolver->expects($this->never())->method('resolveCollectionUrl');

        $table = new TestDataTableWithData(apiResourceCollectionUrlResolver: $resolver);

        $table->prepareForRendering();

        $this->assertNull($table->getDataTable()->getOption('ajax'));
    }

    public function testPrepareForRenderingDoesNothingWithoutAttribute(): void
    {
        $resolver = $this->createMock(ApiResourceCollectionUrlResolverInterface::class);
        $resolver->expects($this->never())->method('resolveCollectionUrl');

        $table = new TestDataTableWithoutAttribute(apiResourceCollectionUrlResolver: $resolver);

        $table->prepareForRendering();

        $this->assertNull($table->getDataTable()->getOption('ajax'));
    }

    public function testPrepareForRenderingDoesNothingWithoutResolver(): void
    {
        $table = new TestDataTableWithAttribute();

        $table->prepareForRendering();

        $this->assertNull($table->getDataTable()->getOption('ajax'));
    }
}
