<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\Attribute;

use Doctrine\ORM\EntityManagerInterface;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ApiResourceCollectionUrlResolverInterface;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\DataProvider\ArrayDataProvider;
use Pentiminax\UX\DataTables\DataProvider\DoctrineDataProvider;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;
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

        // Should return the manually configured provider, not auto-configured
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

        // Should return the same instance (cached)
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
            'type'    => 'GET',
            'url'     => '/api/books',
            'dataSrc' => 'member',
        ], $table->getDataTable()->getOption('ajax'));
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
    }

    public function testPrepareForRenderingDoesNothingWhenServerSideIsEnabled(): void
    {
        $resolver = $this->createMock(ApiResourceCollectionUrlResolverInterface::class);
        $resolver->expects($this->never())->method('resolveCollectionUrl');

        $table = new TestDataTableWithServerSide(apiResourceCollectionUrlResolver: $resolver);

        $table->prepareForRendering();

        $this->assertNull($table->getDataTable()->getOption('ajax'));
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

/**
 * Test fixture: DataTable with AsDataTable attribute.
 */
#[AsDataTable(entityClass: \stdClass::class)]
class TestDataTableWithAttribute extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }
}

#[AsDataTable(entityClass: \stdClass::class)]
class TestDataTableWithManualAjax extends AbstractDataTable
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->ajax('/custom-endpoint');
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }
}

#[AsDataTable(entityClass: \stdClass::class)]
class TestDataTableWithServerSide extends AbstractDataTable
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->serverSide(true);
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }
}

#[AsDataTable(entityClass: \stdClass::class)]
class TestDataTableWithData extends AbstractDataTable
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->data([['id' => 1]]);
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }
}

/**
 * Test fixture: DataTable with AsDataTable attribute but manual override.
 */
#[AsDataTable(entityClass: \stdClass::class)]
class TestDataTableWithManualOverride extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }

    public function getDataProvider(): ?DataProviderInterface
    {
        return new ArrayDataProvider([], $this->rowMapper());
    }
}

/**
 * Test fixture: DataTable without AsDataTable attribute.
 */
class TestDataTableWithoutAttribute extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }
}

#[AsDataTable(entityClass: ToggleEntityFixture::class)]
class TestDataTableWithBooleanColumn extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield BooleanColumn::new('isEmailAuthEnabled');
    }
}

final class ToggleEntityFixture
{
    public bool $isEmailAuthEnabled = true;
}
