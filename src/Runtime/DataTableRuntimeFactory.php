<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Runtime;

use Doctrine\ORM\EntityManagerInterface;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\ActionRowDataResolver;
use Pentiminax\UX\DataTables\Column\TemplateColumnRenderer;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\DataProvider\AutoDataProviderFactory;
use Pentiminax\UX\DataTables\DataProvider\DataProviderResolver;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\RowMapper\RowProcessingPipeline;

final class DataTableRuntimeFactory
{
    public function __construct(
        private ?DataProviderResolver $dataProviderResolver = null,
        private readonly ?TemplateColumnRenderer $templateColumnRenderer = null,
        private readonly ?ActionRowDataResolver $actionRowDataResolver = null,
    ) {
    }

    public function setEntityManager(?EntityManagerInterface $em): void
    {
        $this->getDataProviderResolver()->setEntityManager($em);
    }

    /**
     * @param ColumnInterface[]     $columns
     * @param \Closure(mixed):array $baseMapper
     */
    public function createRowMapper(\Closure $baseMapper, array $columns): RowMapperInterface
    {
        return new RowProcessingPipeline(
            baseMapper: $baseMapper,
            columns: $columns,
            templateColumnRenderer: $this->templateColumnRenderer,
            actionRowDataResolver: $this->actionRowDataResolver ?? new ActionRowDataResolver(),
        );
    }

    /**
     * @param ColumnInterface[] $columns
     */
    public function createRuntime(
        DataTable $table,
        array $columns,
        ?AsDataTable $asDataTable,
        \Closure $baseMapper,
        \Closure $manualDataProviderFactory,
        callable $queryBuilderConfigurator,
    ): DataTableRuntime {
        $rowMapper = $this->createRowMapper($baseMapper, $columns);

        return new DataTableRuntime(
            table: $table,
            dataProviderFactory: function () use ($manualDataProviderFactory, $asDataTable, $rowMapper, $queryBuilderConfigurator): ?DataProviderInterface {
                return $this->getDataProviderResolver()->resolve(
                    manualDataProvider: $manualDataProviderFactory(),
                    asDataTable: $asDataTable,
                    rowMapper: $rowMapper,
                    queryBuilderConfigurator: $queryBuilderConfigurator,
                );
            },
        );
    }

    private function getDataProviderResolver(): DataProviderResolver
    {
        return $this->dataProviderResolver ??= new DataProviderResolver(
            autoDataProviderFactory: new AutoDataProviderFactory()
        );
    }
}
