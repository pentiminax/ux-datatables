<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\ApiPlatform\ApiResourceCollectionUrlResolver;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
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
final class AbstractDataTableApiPlatformUnavailableTest extends TestCase
{
    #[Test]
    #[DataProvider('provideUnresolvableCollections')]
    public function it_reads_no_rows_for_an_api_platform_table_without_a_collection(
        string $tableClass,
        ?ApiResourceCollectionUrlResolver $urlResolver,
    ): void {
        $table = new $tableClass($urlResolver);

        $dataTable = $table->getDataTable();

        $this->assertNull($dataTable->getOption('ajax'));
        $this->assertNull($dataTable->getOption('data'));
        $this->assertNotTrue($dataTable->getOption('apiPlatform'));
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

        yield 'attribute, no collection operation' => [AttributeApiPlatformUnavailableFixture::class, $urlResolver];

        yield 'attribute, API Platform not installed' => [AttributeApiPlatformUnavailableFixture::class, null];

        yield 'fluent opt-in, no collection operation' => [FluentApiPlatformUnavailableFixture::class, $urlResolver];

        yield 'fluent opt-in, API Platform not installed' => [FluentApiPlatformUnavailableFixture::class, null];
    }
}

abstract class ApiPlatformUnavailableFixture extends AbstractDataTable
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

    /**
     * Reading through Doctrine would skip the authorization the collection operation applies.
     */
    protected function createDataProvider(): ?DataProviderInterface
    {
        throw new \LogicException('An API Platform table without a collection must read no rows.');
    }
}

#[AsDataTable(entityClass: \stdClass::class, apiPlatform: true)]
final class AttributeApiPlatformUnavailableFixture extends ApiPlatformUnavailableFixture
{
}

#[AsDataTable(entityClass: \stdClass::class)]
final class FluentApiPlatformUnavailableFixture extends ApiPlatformUnavailableFixture
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->apiPlatform();
    }
}
