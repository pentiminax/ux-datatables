<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Runtime;

use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Column\Rendering\ActionRowDataResolver;
use Pentiminax\UX\DataTables\Column\Rendering\TemplateColumnRenderer;
use Pentiminax\UX\DataTables\Column\Rendering\UrlColumnDataResolver;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\DataProvider\AutoDataProviderFactory;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\RowMapper\RowProcessingPipeline;
use Pentiminax\UX\DataTables\RowMapper\Stage\BooleanSwitchMetadataStage;
use Pentiminax\UX\DataTables\RowMapper\Stage\IconColumnResolutionStage;
use Pentiminax\UX\DataTables\RowMapper\Stage\NormalizationStage;
use Pentiminax\UX\DataTables\Security\PermissionChecker;

final class DataTableRuntimeFactory
{
    private ?ColumnResolver $columnResolver = null;

    public function __construct(
        private ?AutoDataProviderFactory $autoDataProviderFactory = null,
        private readonly ?TemplateColumnRenderer $templateColumnRenderer = null,
        private readonly ?ActionRowDataResolver $actionRowDataResolver = null,
        private readonly ?UrlColumnDataResolver $urlColumnDataResolver = null,
        private readonly ?PermissionChecker $permissionChecker = null,
    ) {
    }

    /**
     * @param ColumnInterface[]     $columns
     * @param \Closure(mixed):array $baseMapper
     */
    public function createRowMapper(\Closure $baseMapper, array $columns): RowMapperInterface
    {
        return (new RowProcessingPipeline(
            $baseMapper,
            $columns,
            $this->columnResolver(),
            $this->urlColumnDataResolver  ?? new UrlColumnDataResolver(),
            $this->templateColumnRenderer ?? new TemplateColumnRenderer(),
            $this->actionRowDataResolver  ?? new ActionRowDataResolver(),
        ))
            ->add(new NormalizationStage())
            ->add(new IconColumnResolutionStage())
            ->add(new BooleanSwitchMetadataStage());
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
        ?callable $configureBaseQueryBuilder = null,
    ): DataTableRuntime {
        $rowMapper = $this->createRowMapper($baseMapper, $columns);

        // An export writes only the exportable columns, so its mapper is built from them alone:
        // template rendering and action resolution then have nothing to do, instead of running Twig,
        // voters, URL generation and a CSRF token for every one of a full table's rows.
        $exportRowMapper = $this->createRowMapper(
            baseMapper: $baseMapper,
            columns: $this->columnResolver()->filterExportable($columns),
        );

        return new DataTableRuntime(
            table: $table,
            dataProviderFactory: fn (): ?DataProviderInterface => $this->createDataProvider(
                manualDataProviderFactory: $manualDataProviderFactory,
                asDataTable: $asDataTable,
                rowMapper: $rowMapper,
                configureQueryBuilder: $configureQueryBuilder,
                exportRowMapper: $exportRowMapper,
                pageProjector: $pageProjector,
                configureBaseQueryBuilder: $configureBaseQueryBuilder,
            ),
        );
    }

    private function createDataProvider(
        \Closure $manualDataProviderFactory,
        ?AsDataTable $asDataTable,
        RowMapperInterface $rowMapper,
        callable $configureQueryBuilder,
        ?RowMapperInterface $exportRowMapper = null,
        ?\Closure $pageProjector = null,
        ?callable $configureBaseQueryBuilder = null,
    ): ?DataProviderInterface {
        return $manualDataProviderFactory() ?? $this->getAutoDataProviderFactory()->create(
            asDataTable: $asDataTable,
            rowMapper: $rowMapper,
            configureQueryBuilder: $configureQueryBuilder,
            exportRowMapper: $exportRowMapper,
            pageProjector: $pageProjector,
            configureBaseQueryBuilder: $configureBaseQueryBuilder,
        );
    }

    private function columnResolver(): ColumnResolver
    {
        return $this->columnResolver ??= new ColumnResolver(
            permissionChecker: $this->permissionChecker ?? new PermissionChecker(),
        );
    }

    private function getAutoDataProviderFactory(): AutoDataProviderFactory
    {
        return $this->autoDataProviderFactory ??= new AutoDataProviderFactory();
    }
}
