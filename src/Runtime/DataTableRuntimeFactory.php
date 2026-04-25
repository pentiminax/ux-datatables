<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Runtime;

use Doctrine\ORM\EntityManagerInterface;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\Rendering\ActionRowDataResolver;
use Pentiminax\UX\DataTables\Column\Rendering\TemplateColumnRenderer;
use Pentiminax\UX\DataTables\Column\Rendering\UrlColumnDataResolver;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\DataProvider\AutoDataProviderFactory;
use Pentiminax\UX\DataTables\DataProvider\DataProviderResolver;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\RowMapper\RowProcessingPipeline;
use Pentiminax\UX\DataTables\RowMapper\Stage\ActionResolutionStage;
use Pentiminax\UX\DataTables\RowMapper\Stage\NormalizationStage;
use Pentiminax\UX\DataTables\RowMapper\Stage\TemplateRenderingStage;
use Pentiminax\UX\DataTables\RowMapper\Stage\UrlColumnResolutionStage;

final class DataTableRuntimeFactory
{
    public function __construct(
        private ?DataProviderResolver $dataProviderResolver = null,
        private readonly ?TemplateColumnRenderer $templateColumnRenderer = null,
        private readonly ?ActionRowDataResolver $actionRowDataResolver = null,
        private readonly ?UrlColumnDataResolver $urlColumnDataResolver = null,
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
        $pipeline = (new RowProcessingPipeline($baseMapper, $columns))
            ->add(new NormalizationStage());

        $pipeline->add(new UrlColumnResolutionStage($this->urlColumnDataResolver ?? new UrlColumnDataResolver()));

        if (null !== $this->templateColumnRenderer) {
            $pipeline->add(new TemplateRenderingStage($this->templateColumnRenderer));
        }

        $pipeline->add(new ActionResolutionStage($this->actionRowDataResolver ?? new ActionRowDataResolver()));

        return $pipeline;
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
            dataProviderFactory: fn (): ?DataProviderInterface => $this->createDataProvider(
                $manualDataProviderFactory,
                $asDataTable,
                $rowMapper,
                $queryBuilderConfigurator,
            ),
        );
    }

    private function createDataProvider(
        \Closure $manualDataProviderFactory,
        ?AsDataTable $asDataTable,
        RowMapperInterface $rowMapper,
        callable $queryBuilderConfigurator,
    ): ?DataProviderInterface {
        return $this->getDataProviderResolver()->resolve(
            manualDataProvider: $manualDataProviderFactory(),
            asDataTable: $asDataTable,
            rowMapper: $rowMapper,
            queryBuilderConfigurator: $queryBuilderConfigurator,
        );
    }

    private function getDataProviderResolver(): DataProviderResolver
    {
        return $this->dataProviderResolver ??= new DataProviderResolver(
            autoDataProviderFactory: new AutoDataProviderFactory()
        );
    }
}
