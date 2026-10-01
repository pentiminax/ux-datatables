<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\ApiPlatform\ApiResourceCollectionUrlResolver;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\DataProvider\ArrayDataProvider;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Runtime\DataTableInfrastructure;
use Pentiminax\UX\DataTables\Runtime\RenderingPreparer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AbstractDataTable::class)]
final class AbstractDataTableApiPlatformFallbackTest extends TestCase
{
    #[Test]
    #[DataProvider('provideUnresolvableCollections')]
    public function it_embeds_the_rows_of_a_client_side_api_platform_table_without_a_collection_url(
        string $tableClass,
        ?ApiResourceCollectionUrlResolver $urlResolver,
    ): void {
        $table = new $tableClass($urlResolver);

        $dataTable = $table->getDataTable();

        $this->assertNull($dataTable->getOption('ajax'));
        $this->assertNotTrue($dataTable->getOption('apiPlatform'));
        $this->assertSame([['id' => 1], ['id' => 2]], $dataTable->getOption('data'));
    }

    public static function provideUnresolvableCollections(): iterable
    {
        $urlResolver = new class extends ApiResourceCollectionUrlResolver {
            public function __construct()
            {
            }

            public function resolveCollectionUrl(string $entityClass): ?string
            {
                return null;
            }
        };

        yield 'attribute, no collection operation' => [AttributeApiPlatformFallbackFixture::class, $urlResolver];

        yield 'attribute, API Platform not installed' => [AttributeApiPlatformFallbackFixture::class, null];

        yield 'fluent opt-in, no collection operation' => [FluentApiPlatformFallbackFixture::class, $urlResolver];

        yield 'fluent opt-in, API Platform not installed' => [FluentApiPlatformFallbackFixture::class, null];
    }
}

abstract class ApiPlatformFallbackFixture extends AbstractDataTable
{
    public function __construct(?ApiResourceCollectionUrlResolver $urlResolver)
    {
        $this->setDataTableInfrastructure(DataTableInfrastructure::createDefault(
            renderingPreparer: new RenderingPreparer(urlResolver: $urlResolver),
        ));
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }

    protected function createDataProvider(): ?DataProviderInterface
    {
        return new ArrayDataProvider([['id' => 1], ['id' => 2]], $this->createRowMapper());
    }
}

#[AsDataTable(entityClass: \stdClass::class, apiPlatform: true)]
final class AttributeApiPlatformFallbackFixture extends ApiPlatformFallbackFixture
{
}

#[AsDataTable(entityClass: \stdClass::class)]
final class FluentApiPlatformFallbackFixture extends ApiPlatformFallbackFixture
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->apiPlatform();
    }
}
