<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Runtime;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Column\Rendering\ActionRowDataResolver;
use Pentiminax\UX\DataTables\Column\Rendering\TemplateColumnRenderer;
use Pentiminax\UX\DataTables\Column\Rendering\UrlColumnDataResolver;
use Pentiminax\UX\DataTables\Column\VisibleColumns;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\DataProvider\AutoDataProviderFactory;
use Pentiminax\UX\DataTables\DataTableRequest\Column as RequestColumn;
use Pentiminax\UX\DataTables\DataTableRequest\Columns as RequestColumns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Highlight\HighlightConfig;
use Pentiminax\UX\DataTables\Mercure\MercureConfig;
use Pentiminax\UX\DataTables\Mercure\MercureTableConfigResolver;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Profiler\DataTableProfiler;
use Pentiminax\UX\DataTables\Query\Builder\QueryFilterPipeline;
use Pentiminax\UX\DataTables\RowMapper\RowIdField;
use Pentiminax\UX\DataTables\RowMapper\RowProcessingPipeline;
use Pentiminax\UX\DataTables\RowMapper\Stage\BooleanSwitchMetadataStage;
use Pentiminax\UX\DataTables\RowMapper\Stage\IconColumnResolutionStage;
use Pentiminax\UX\DataTables\RowMapper\Stage\NormalizationStage;
use Pentiminax\UX\DataTables\RowMapper\Stage\RowIdStage;
use Pentiminax\UX\DataTables\RowMapper\Stage\RowPresentationStage;
use Pentiminax\UX\DataTables\RowMapper\Stage\ValueFormattingStage;
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Serves a table: renders it, and answers its Ajax data, export, row action and bulk requests.
 *
 * A stateless service. It takes the table (its configuration comes from {@see TableDefinitionBuilder},
 * its behavior from the hooks the table overrides) and the request, and keeps the per-request
 * state, permission filtering included, in a {@see DataTableCall} that dies with the request.
 */
final class DataTableRuntime
{
    private ?SearchListOptionsResolver $searchListOptionsResolver;

    public function __construct(
        private readonly TableDefinitionBuilder $definitions,
        private readonly RenderingPreparer $renderingPreparer,
        private readonly ColumnResolver $columnResolver,
        private readonly AuthorizationChecker $permissionChecker,
        private readonly QueryFilterPipeline $queryFilterPipeline,
        private readonly AutoDataProviderFactory $autoDataProviderFactory = new AutoDataProviderFactory(),
        private readonly ?TemplateColumnRenderer $templateColumnRenderer = null,
        private readonly ?ActionRowDataResolver $actionRowDataResolver = null,
        private readonly ?UrlColumnDataResolver $urlColumnDataResolver = null,
        private readonly int $maxPageLength = 1000,
        ?SearchListOptionsResolver $searchListOptionsResolver = null,
        private readonly ?ManagerRegistry $doctrine = null,
        private readonly ?DataTableProfiler $profiler = null,
        private readonly ?LoggerInterface $logger = null,
        private readonly int $hydrationRowThreshold = 0,
        private readonly DataSourceFeatureGuard $featureGuard = new DataSourceFeatureGuard(),
        private readonly MercureTableConfigResolver $mercure = new MercureTableConfigResolver(),
    ) {
        $this->searchListOptionsResolver = $searchListOptionsResolver;
    }

    public function definition(AbstractDataTable $table): TableDefinition
    {
        return $this->definitions->build($table);
    }

    /**
     * Build the payload of one render. The table and its definition are left as configured.
     *
     * @throws \LogicException when the table cannot be rendered as configured
     */
    public function render(AbstractDataTable $table): PreparedDataTable
    {
        $definition = $this->definitions->build($table);
        $call       = new DataTableCall($this, $definition, $table);

        $inlineRows = $table->getInlineRows();
        if (null !== $inlineRows) {
            $definition = $this->definitions->withInlineRows($definition, $this->mapRows($call, $inlineRows));
            $call       = new DataTableCall($this, $definition, $table);
        }

        $optIns = $definition->optIns;

        $this->renderingPreparer->prepareBeforeDataHydration($definition->table, $optIns);

        $this->renderingPreparer->assertMutationsScoped($definition->table, static fn (): ?DataProviderInterface => $call->dataProvider());

        $rows = match (true) {
            DataSourceMode::ClientHydrated === $optIns->dataSourceMode => $this->hydrateClientSideRows($call),
            null !== $inlineRows                                       => null,
            default                                                    => $this->mapExplicitInlineRows($call),
        };

        $bulkRowIdField = $definition->table->hasBulkActions()
            ? $this->resolveRowIdField(null, $definition->table->getBulkActions()?->getIdField(), $definition->entityClass())
            : null;

        // Resolved last, so a table failing both reports the preparer's own error first.
        return $this->renderingPreparer->prepareAfterDataHydration($definition->table, $optIns, $rows, $bulkRowIdField, fn (): ?MercureConfig => $this->mercure->resolve($optIns));
    }

    /**
     * The Mercure configuration the table serializes to the browser, resolved without reading any data.
     *
     * @throws \LogicException if Mercure is enabled but the hub URL cannot be resolved
     */
    public function mercureConfig(AbstractDataTable $table): ?MercureConfig
    {
        $definition = $this->definitions->build($table);

        return $this->mercure->resolve($definition->optIns);
    }

    /**
     * Answers a DataTables Ajax request for a table from a custom endpoint, with Web Profiler
     * collection. Returns null when the request carries no DataTables payload.
     *
     * The caller protects the route: this does not authenticate or authorize the user.
     */
    public function respond(AbstractDataTable $table, Request $request): ?JsonResponse
    {
        $call = $this->handle($table, $request);

        if (!$call->isHandled()) {
            return null;
        }

        $start    = hrtime(true);
        $response = $call->response();

        $this->collectAjaxQueryForProfiler($call, $response, $start);

        return $response;
    }

    /**
     * Binds a table to one HTTP request, for the export, row action and bulk paths that read the
     * provider or the request themselves.
     */
    public function handle(AbstractDataTable $table, Request $request): DataTableCall
    {
        $definition       = $this->definitions->build($table);
        $dataTableRequest = DataTableRequest::fromRequest($request);

        // A refused "show all" falls back to the configured bound: DataTables renders that one
        // page and offers no other, so a smaller page truncates rather than strands rows.
        $maxLength = \in_array($dataTableRequest->length, $this->declaredPageSizes($definition->table), true)
            ? $dataTableRequest->length
            : $this->maxPageLength;

        return new DataTableCall(
            $this,
            $definition,
            $table,
            $request,
            $dataTableRequest->withBoundedLength($maxLength, $this->allowsShowAll($definition->table)),
        );
    }

    /**
     * The table bound to no request, for callers that only resolve its data provider.
     */
    public function open(AbstractDataTable $table): DataTableCall
    {
        return new DataTableCall($this, $this->definitions->build($table), $table);
    }

    /**
     * @internal
     */
    public function guard(): DataSourceFeatureGuard
    {
        return $this->featureGuard;
    }

    /**
     * @internal
     */
    public function visibleColumns(TableDefinition $definition): VisibleColumns
    {
        return $this->columnResolver->visibleColumns($definition->columns, $definition->table->getDataTableClass());
    }

    /**
     * @internal
     */
    public function searchListOptions(DataTableCall $call): ?\stdClass
    {
        $this->searchListOptionsResolver ??= new SearchListOptionsResolver(columnResolver: $this->columnResolver);

        return $this->searchListOptionsResolver->resolve($call->definition->table, $call->definition->columns, $call->request, $call->visibleColumns());
    }

    /**
     * @internal
     */
    public function createRowMapper(DataTableCall $call, bool $visibleColumns = false): RowMapperInterface
    {
        $definition = $call->definition;
        $table      = $definition->table;

        return $this->assembleRowMapper(
            baseMapper: $call->hooks->mapRow(...),
            columns: $definition->columns,
            dataTableClass: $definition->class,
            highlight: $table->getHighlightConfig(),
            rowIdField: $table->hasBulkActions() ? $table->getBulkActions()?->getIdField() : null,
            entityClass: $definition->entityClass(),
            rowClass: $table->getRowClassResolver(),
            recordUrl: $table->getRecordUrlResolver(),
            visibleColumns: $visibleColumns ? $call->visibleColumns(...) : null,
        );
    }

    /**
     * An export writes only the exportable columns, so its mapper is built from them alone: template
     * rendering and action resolution then have nothing to do, instead of running Twig, voters, URL
     * generation and a CSRF token for every one of a full table's rows.
     *
     * @internal
     */
    public function createExportRowMapper(DataTableCall $call): RowMapperInterface
    {
        return $this->assembleRowMapper(
            baseMapper: $call->hooks->mapRow(...),
            columns: $this->columnResolver->filterExportable($call->definition->columns),
            dataTableClass: $call->definition->class,
        );
    }

    /**
     * @throws NotFoundHttpException when the table opts into API Platform with no collection to read
     *
     * @internal
     */
    public function createDataProvider(DataTableCall $call): ?DataProviderInterface
    {
        $definition = $call->definition;

        // Rendering refuses an API Platform table without a collection, and Ajax and export answer 404.
        if (DataSourceMode::ApiPlatformUnavailable === $definition->optIns->dataSourceMode) {
            throw new NotFoundHttpException('DataTable not found.');
        }

        $manual = $call->hooks->createDataProvider();
        if (null !== $manual) {
            return $manual;
        }

        return $this->autoDataProviderFactory->create(
            definition: $definition,
            hooks: $call->hooks,
            rowMapper: $call->rowMapper(),
            queryFilterPipeline: $this->queryFilterPipeline,
            visibleColumns: $call->visibleColumns(),
            exportRowMapper: $call->exportRowMapper(),
        );
    }

    /**
     * @param \Closure(mixed):array<string, mixed> $baseMapper
     * @param array<array-key, mixed>              $columns
     * @param (\Closure():VisibleColumns)|null     $visibleColumns
     */
    private function assembleRowMapper(
        \Closure $baseMapper,
        array $columns,
        ?string $dataTableClass = null,
        ?HighlightConfig $highlight = null,
        ?string $rowIdField = null,
        ?string $entityClass = null,
        ?\Closure $rowClass = null,
        ?\Closure $recordUrl = null,
        ?\Closure $visibleColumns = null,
    ): RowMapperInterface {
        $pipeline = (new RowProcessingPipeline(
            $baseMapper,
            $columns,
            $this->columnResolver,
            $this->actionRowDataResolver  ?? new ActionRowDataResolver($this->permissionChecker),
            $this->urlColumnDataResolver  ?? new UrlColumnDataResolver(),
            $this->templateColumnRenderer ?? new TemplateColumnRenderer(),
            $dataTableClass,
            $visibleColumns,
        ))
            ->add(new NormalizationStage())
            ->add(new ValueFormattingStage())
            ->add(new IconColumnResolutionStage())
            ->add(new BooleanSwitchMetadataStage($this->resolveRowIdField(highlight: null, rowIdField: 'id', entityClass: $entityClass), $entityClass));

        // Highlighting needs a row id to tell a changed row from a moved one; a selection needs
        // one to survive the redraw that server-side paging forces. Either reason is enough.
        $idField = $this->resolveRowIdField($highlight, $rowIdField, $entityClass);

        if (null !== $idField) {
            $pipeline->add(new RowIdStage($idField));
        }

        if (null !== $rowClass || null !== $recordUrl) {
            $pipeline->add(new RowPresentationStage($rowClass, $recordUrl));
        }

        return $pipeline;
    }

    /**
     * The field written as `DT_RowId`, with the default bulk `id` remapped onto the entity identifier.
     */
    private function resolveRowIdField(?HighlightConfig $highlight, ?string $rowIdField, ?string $entityClass): ?string
    {
        return RowIdField::resolve($highlight, $rowIdField, fn (): ?ClassMetadata => $this->metadataFor($entityClass));
    }

    /**
     * @param class-string|null $entityClass
     *
     * @return ClassMetadata<object>|null
     */
    private function metadataFor(?string $entityClass): ?ClassMetadata
    {
        if (null === $entityClass) {
            return null;
        }

        return $this->doctrine?->getManagerForClass($entityClass)?->getClassMetadata($entityClass);
    }

    /**
     * @param iterable<mixed> $data
     *
     * @return list<array<string, mixed>>
     */
    private function mapRows(DataTableCall $call, iterable $data): array
    {
        $rowMapper = $this->createRowMapper($call);
        $rows      = [];

        foreach ($data as $item) {
            $rows[] = $rowMapper->map($item);
        }

        return $rows;
    }

    /**
     * Rows set through data() still need the row pipeline.
     *
     * @return list<array<string, mixed>>|null
     */
    private function mapExplicitInlineRows(DataTableCall $call): ?array
    {
        $data = $call->definition->table->getOption('data');

        return null === $data ? null : $this->mapRows($call, $data);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function hydrateClientSideRows(DataTableCall $call): array
    {
        $rows = $call->fetchData($this->createClientSideDataRequest($call->definition))->data;
        $rows = array_values(\is_array($rows) ? $rows : iterator_to_array($rows));

        $this->warnWhenHydrationIsLarge($call->definition, \count($rows));

        return $rows;
    }

    private function warnWhenHydrationIsLarge(TableDefinition $definition, int $rows): void
    {
        if ($this->hydrationRowThreshold <= 0 || $rows <= $this->hydrationRowThreshold) {
            return;
        }

        $this->logger?->warning(\sprintf('The table "%s" embeds %d rows in the page, above the hydration_row_threshold of %d. Call serverSide() so the browser fetches one page at a time.', $definition->class, $rows, $this->hydrationRowThreshold), [
            'table' => $definition->class,
            'rows'  => $rows,
        ]);
    }

    private function createClientSideDataRequest(TableDefinition $definition): DataTableRequest
    {
        $columns = [];
        foreach ($definition->columns as $column) {
            $columns[$column->getName()] = new RequestColumn(
                data: $column->getData() ?? $column->getName(),
                name: $column->getName(),
                searchable: $column->isSearchable(),
                orderable: $column->isOrderable(),
            );
        }

        return new DataTableRequest(
            draw: null,
            columns: new RequestColumns($columns),
            start: 0,
            length: 0,
            rowsOnly: true,
        );
    }

    /**
     * The page sizes the table itself offers, through pageLength() and lengthMenu().
     *
     * A declared size is a developer decision, not crafted input, and the client paginates with
     * it, so it escapes the bound: capping it below the length the client paginates with would
     * leave the rows between the two sizes unreachable. Only those exact sizes escape it -- a
     * table declaring 2000 must not turn an arbitrary length=1500 into a served page.
     *
     * @return list<int>
     */
    private function declaredPageSizes(DataTable $table): array
    {
        $declared = [$table->getOption('pageLength'), ...$this->lengthMenuValues($table)];

        return array_values(array_filter(
            array_map(
                static fn (mixed $value): int => is_numeric($value) ? (int) $value : 0,
                $declared,
            ),
            static fn (int $value): bool => $value > 0,
        ));
    }

    /**
     * Whether the table offers DataTables' "show all" entry, the only case where an
     * unbounded page size is what the developer asked for.
     */
    private function allowsShowAll(DataTable $table): bool
    {
        foreach ($this->lengthMenuValues($table) as $value) {
            if (-1 === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * The page sizes declared through lengthMenu(), which accepts `[10, 25, -1]`, the
     * two-dimensional `[[10, 25, -1], ['10', '25', 'All']]` form, and associative entries such
     * as `['label' => 'All', 'value' => -1]`.
     *
     * @return list<mixed>
     */
    private function lengthMenuValues(DataTable $table): array
    {
        $menu = $table->getOption('lengthMenu');

        if (!\is_array($menu)) {
            return [];
        }

        $entries = isset($menu[0]) && \is_array($menu[0]) && array_is_list($menu[0])
            ? $menu[0]
            : $menu;

        $values = [];
        foreach ($entries as $entry) {
            $values[] = \is_array($entry) ? ($entry['value'] ?? null) : $entry;
        }

        return $values;
    }

    /**
     * Records this Ajax response with the Web Profiler, whichever controller built it. A no-op
     * when no profiler is wired.
     */
    private function collectAjaxQueryForProfiler(DataTableCall $call, JsonResponse $response, float $start): void
    {
        if (null === $this->profiler) {
            return;
        }

        $payload  = json_decode((string) $response->getContent(), true);
        $payload  = \is_array($payload) ? $payload : [];
        $provider = $call->dataProvider();

        $this->profiler->collectAjaxQuery(
            class: $call->definition->class,
            token: $call->httpRequest?->query->getString('table') ?: null,
            request: $call->request,
            recordsTotal: (int) ($payload['recordsTotal'] ?? 0),
            recordsFiltered: (int) ($payload['recordsFiltered'] ?? 0),
            durationMs: (hrtime(true) - $start) / 1_000_000,
            providerClass: null !== $provider ? $provider::class : null,
            entityClass: $call->definition->entityClass(),
            rowCount: \is_array($payload['data'] ?? null) ? \count($payload['data']) : 0,
            payloadBytes: \strlen((string) $response->getContent()),
            httpStatus: $response->getStatusCode(),
        );
    }
}
