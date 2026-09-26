<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\Rendering\TemplateColumnRenderer;
use Pentiminax\UX\DataTables\Column\TemplateColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\DataProvider\ApiPlatformCollectionProvider;
use Pentiminax\UX\DataTables\DataProvider\AutoDataProviderFactory;
use Pentiminax\UX\DataTables\DataTableRequest\Column;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Runtime\DataTableInfrastructure;
use Pentiminax\UX\DataTables\Runtime\DataTableRuntimeFactory;
use Pentiminax\UX\DataTables\Tests\Support\BuildsApiPlatformProviderFactory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * End-to-end cover for a DTO-shaped table whose rows are read through an API Platform collection.
 *
 * @internal
 */
#[CoversNothing]
final class AbstractDataTableApiPlatformProjectionTest extends TestCase
{
    use BuildsApiPlatformProviderFactory;

    #[Test]
    public function it_renders_projected_rows_through_the_table_row_mapper(): void
    {
        $table = $this->table();

        $this->assertInstanceOf(ApiPlatformCollectionProvider::class, $table->getDataProvider());

        $result = $table->fetchData($this->request());

        $this->assertSame(2, $result->recordsTotal);
        $this->assertSame([
            ['id' => 1, 'label' => 'BOOK:Dune', 'badge' => '<b>BOOK:Dune</b> from Dune'],
            ['id' => 2, 'label' => 'BOOK:Emma', 'badge' => '<b>BOOK:Emma</b> from Emma'],
        ], iterator_to_array($result->data, false));
    }

    private function table(): AbstractDataTable
    {
        $stateProvider = new class implements ProviderInterface {
            public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
            {
                return [new ProjectedApiBook(1, 'Dune'), new ProjectedApiBook(2, 'Emma')];
            }
        };

        $table = new ProjectedApiBooksDataTable();
        $table->setDataTableInfrastructure(DataTableInfrastructure::createDefault(
            runtimeFactory: new DataTableRuntimeFactory(
                autoDataProviderFactory: new AutoDataProviderFactory(
                    apiPlatformProviderFactory: $this->buildApiPlatformProviderFactory(stateProvider: $stateProvider),
                ),
                templateColumnRenderer: new TemplateColumnRenderer(new Environment(new ArrayLoader([
                    'datatable/columns/book_badge.html.twig' => '<b>{{ row.label }}</b> from {{ source.title }}',
                ]))),
            ),
        ));

        return $table;
    }

    private function request(): DataTableRequest
    {
        return new DataTableRequest(
            draw: 1,
            columns: new Columns([
                'id'    => new Column(data: 'id', name: 'id', searchable: false, orderable: false),
                'label' => new Column(data: 'label', name: 'label', searchable: false, orderable: false),
                'badge' => new Column(data: 'badge', name: 'badge', searchable: false, orderable: false),
            ]),
            start: 0,
            length: 10,
        );
    }
}

final readonly class ProjectedApiBook
{
    public function __construct(
        public int $id,
        public string $title,
    ) {
    }
}

final readonly class ProjectedApiBookRow
{
    public function __construct(
        public int $id,
        public string $label,
    ) {
    }
}

#[AsDataTable(dataClass: ProjectedApiBookRow::class, entityClass: ProjectedApiBook::class, apiPlatform: true)]
final class ProjectedApiBooksDataTable extends AbstractDataTable
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->serverSide(true);
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
        yield TextColumn::new('label');
        yield TemplateColumn::new('badge')
            ->setField('label')
            ->setTemplate('datatable/columns/book_badge.html.twig');
    }

    protected function projectPage(array $items): ?array
    {
        return array_map(
            static fn (ProjectedApiBook $book): ProjectedApiBookRow => new ProjectedApiBookRow($book->id, 'BOOK:'.$book->title),
            $items,
        );
    }
}
