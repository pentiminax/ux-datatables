<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Runtime;

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
use Pentiminax\UX\DataTables\RowMapper\Stage\BooleanSwitchMetadataStage;
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

    /**
     * @param ColumnInterface[]     $columns
     * @param \Closure(mixed):array $baseMapper
     */
    public function createRowMapper(\Closure $baseMapper, array $columns): RowMapperInterface
    {
        $pipeline = (new RowProcessingPipeline($baseMapper, $columns))
            ->add(new NormalizationStage())
            ->add(new BooleanSwitchMetadataStage());

        $pipeline->add(new UrlColumnResolutionStage($this->urlColumnDataResolver ?? new UrlColumnDataResolver()));

        $pipeline->add(new TemplateRenderingStage($this->templateColumnRenderer ?? new TemplateColumnRenderer()));

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
        callable $configureQueryBuilder,
        ?\Closure $pageProjector = null,
    ): DataTableRuntime {
        $rowMapper = $this->createRowMapper($baseMapper, $columns);

        return new DataTableRuntime(
            table: $table,
            dataProviderFactory: fn (): ?DataProviderInterface => $this->createDataProvider(
                manualDataProviderFactory: $manualDataProviderFactory,
                asDataTable: $asDataTable,
                rowMapper: $rowMapper,
                configureQueryBuilder: $configureQueryBuilder,
                pageProjector: $pageProjector,
            ),
        );
    }

    private function createDataProvider(
        \Closure $manualDataProviderFactory,
        ?AsDataTable $asDataTable,
        RowMapperInterface $rowMapper,
        callable $configureQueryBuilder,
        ?\Closure $pageProjector = null,
    ): ?DataProviderInterface {
        return $this->getDataProviderResolver()->resolve(
            manualDataProvider: $manualDataProviderFactory(),
            asDataTable: $asDataTable,
            rowMapper: $rowMapper,
            configureQueryBuilder: $configureQueryBuilder,
            pageProjector: $pageProjector,
        );
    }

    private function getDataProviderResolver(): DataProviderResolver
    {
        return $this->dataProviderResolver ??= new DataProviderResolver(
            autoDataProviderFactory: new AutoDataProviderFactory()
        );
    }
}
