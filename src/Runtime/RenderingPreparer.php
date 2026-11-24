<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Runtime;

use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\ApiPlatform\ApiResourceCollectionUrlResolver;
use Pentiminax\UX\DataTables\Column\AbstractColumn;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Column\ColumnTranslator;
use Pentiminax\UX\DataTables\Column\UrlColumn;
use Pentiminax\UX\DataTables\Contracts\ActionsProvidingColumnInterface;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\TemplateAwareColumnInterface;
use Pentiminax\UX\DataTables\Mercure\MercureConfig;
use Pentiminax\UX\DataTables\Model\BulkActions;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Extensions\ButtonsExtension;
use Pentiminax\UX\DataTables\Model\FilterLabels;
use Pentiminax\UX\DataTables\Model\Filters;
use Pentiminax\UX\DataTables\Model\Summary\SummaryType;
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;
use Pentiminax\UX\DataTables\Security\MutationTokenValidator;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
final class RenderingPreparer
{
    public const string AJAX_DATA_ROUTE   = 'ux_datatables_ajax_data';
    public const string AJAX_EXPORT_ROUTE = 'ux_datatables_ajax_export';
    public const string AJAX_BULK_ROUTE   = 'ux_datatables_ajax_bulk';

    private const array ROW_ACTION_ROUTES = [
        'delete'         => 'ux_datatables_ajax_delete',
        'toggle'         => 'ux_datatables_ajax_edit',
        'detail'         => 'ux_datatables_ajax_detail',
        'editForm'       => 'ux_datatables_ajax_edit_form',
        'editFormSubmit' => 'ux_datatables_ajax_edit_form_submit',
    ];

    /**
     * Chrome strings of the bulk action bar, translated server-side like the filter bar's.
     *
     * @var array<string, string>
     */
    private const array BULK_LABEL_KEYS = [
        'trigger'             => 'bulk.bar.trigger',
        'selected'            => 'bulk.bar.selected',
        'selectAllMatching'   => 'bulk.bar.selectAllMatching',
        'allMatchingSelected' => 'bulk.bar.allMatchingSelected',
        'clear'               => 'bulk.bar.clear',
        'confirm'             => 'bulk.bar.confirm',
        'cancel'              => 'bulk.bar.cancel',
        'processed'           => 'bulk.bar.processed',
        'skipped'             => 'bulk.bar.skipped',
        'failed'              => 'bulk.bar.failed',
    ];

    /**
     * Messages and confirmation buttons of the built-in row actions (delete, toggle, detail, edit),
     * translated server-side like the bulk bar's.
     *
     * @var array<string, string>
     */
    private const array ACTION_LABEL_KEYS = [
        'failed'    => 'action.row.failed',
        'forbidden' => 'action.row.forbidden',
        'confirm'   => 'action.row.confirm',
        'cancel'    => 'action.row.cancel',
    ];

    /**
     * Built-in strings of the empty state, translated server-side like the bulk bar's.
     *
     * @var array<string, string>
     */
    private const array EMPTY_STATE_LABEL_KEYS = [
        'noResults'     => 'empty.noResults',
        'noResultsHint' => 'empty.noResultsHint',
        'resetFilters'  => 'empty.resetFilters',
    ];

    public function __construct(
        private readonly AuthorizationChecker $permissionChecker,
        private readonly ColumnResolver $columnResolver,
        private readonly ?ApiResourceCollectionUrlResolver $urlResolver = null,
        private readonly ?TranslatorInterface $translator = null,
        private readonly ?UrlGeneratorInterface $urlGenerator = null,
        private readonly ?AjaxDataTableRegistry $ajaxRegistry = null,
        private readonly ?RequestStack $requestStack = null,
        private readonly ?SearchListOptionsResolver $searchListOptionsResolver = null,
        private readonly ?FilterEntityOptionsResolver $filterEntityOptionsResolver = null,
        private readonly DataSourceFeatureGuard $featureGuard = new DataSourceFeatureGuard(),
        private readonly ?CsrfTokenManagerInterface $csrfTokenManager = null,
    ) {
    }

    /**
     * Validate the table before its rows are read.
     *
     * @throws \LogicException when the table opts into API Platform with no collection to read
     */
    public function prepareBeforeDataHydration(DataTable $table, ResolvedOptIns $optIns): void
    {
        if (DataSourceMode::ApiPlatformUnavailable === $optIns->dataSourceMode) {
            throw new \LogicException(\sprintf('Table "%s" opts into API Platform but "%s" exposes no collection operation, or API Platform is not installed. Expose a collection operation or remove the apiPlatform opt-in.', $table->getDataTableClass() ?? $table->getId(), $optIns->entityClass ?? 'no entity class'));
        }

        $this->featureGuard->assertConfigurable($table, $optIns);
    }

    /**
     * @param \Closure():?DataProviderInterface $provider read only when the table has actions served by the bundle
     *
     * @throws \LogicException when the table's actions run on a data provider that cannot check the row scope
     */
    public function assertMutationsScoped(DataTable $table, \Closure $provider): void
    {
        if (!DataSourceFeatureGuard::hasRowActions($table) && !$table->hasBulkActions()) {
            return;
        }

        $dataProvider = $provider();

        if (null !== $dataProvider) {
            $this->featureGuard->assertMutationsScoped($table, $dataProvider);
        }
    }

    /**
     * Build the payload of one render. Nothing is written back to the table.
     *
     * @param list<array<string, mixed>>|null $rows    rows read for this render, replacing the table's `data`
     * @param \Closure(): ?MercureConfig      $mercure resolves the Mercure config once the payload checks have passed
     *
     * @throws \LogicException when the table polls without an Ajax source, or the payload sets a key twice
     */
    public function prepareAfterDataHydration(DataTable $table, ResolvedOptIns $optIns, ?array $rows, ?string $bulkRowIdField, \Closure $mercure): PreparedDataTable
    {
        $dataTableClass    = $table->getDataTableClass();
        $configuredColumns = $this->translatedColumns($table->getColumns());
        $columns           = array_values($this->columnResolver->filterStaticPermissions($configuredColumns, $dataTableClass));

        $options = $table->getOptions();
        if (null !== $rows) {
            $options['data'] = $rows;
        }

        $apiPlatform           = $table->getBundleOption('apiPlatform');
        $apiPlatformServerSide = false;

        if (DataSourceMode::ApiPlatform === $optIns->dataSourceMode) {
            $collectionUrl = null === $optIns->entityClass ? null : $this->urlResolver?->resolveCollectionUrl($optIns->entityClass);

            if (null !== $collectionUrl) {
                $apiPlatform = true;

                // A template, action or resolved URL column renders from the source entity, which the
                // browser never holds. Those tables read the collection server-side, through the same
                // operation, and the Stimulus adapter stays out of the way.
                $serverSideAjax = $this->hasEntityDependentColumn($table) ? $this->bundleAjax($dataTableClass) : null;

                if (null !== $serverSideAjax) {
                    $apiPlatformServerSide = true;
                    $options['serverSide'] = true;
                    $options['ajax']       = $serverSideAjax;
                } else {
                    $options['ajax'] = ['type' => 'GET', 'url' => $collectionUrl];
                }
            }
        }

        if (DataSourceMode::Ajax === $optIns->dataSourceMode && $optIns->optsIntoApiPlatform()) {
            $apiPlatform = true;
        }

        if (DataSourceMode::ServerSide === $optIns->dataSourceMode) {
            $options['serverSide'] = true;
            $bundleAjax            = $this->bundleAjax($dataTableClass);

            if (null !== $bundleAjax) {
                $options['ajax'] = $bundleAjax;
            }
        }

        $forwardedQuery = $this->forwardedQuery($table);
        if (null !== $forwardedQuery && \is_array($options['ajax'] ?? null)) {
            $options['ajax']['data'] = array_merge($options['ajax']['data'] ?? [], $forwardedQuery);
        }

        if (null !== $table->getBundleOption('pollInterval') && null === ($options['ajax'] ?? null)) {
            throw new \LogicException(\sprintf('Polling requires an Ajax source, but "%s" has no ajax option once prepared.', $table->getId()));
        }

        $searchLists        = $this->searchListOptionsResolver ?? new SearchListOptionsResolver($this->translator);
        $options['columns'] = $searchLists->serialize(array_map(static fn (AbstractColumn $column): array => $column->jsonSerialize(), $columns));

        $extensions  = $searchLists->serialize($table->getExtensions());
        $bulkActions = $this->allowedBulkActions($table->getBulkActions(), $dataTableClass);

        // Checkboxes the table only shows for its bulk actions would select rows for nothing.
        if (null === $bulkActions && $table->isSelectionForBulkActions()) {
            unset($extensions['select']);
        }

        $hasRowActions = $this->rendersActionUrls($table);
        $filters       = $this->translatedFilters($table->getFilters());
        $hasFilters    = null !== $filters && !$filters->isEmpty();
        $emptyState    = $table->getEmptyState();

        return new PreparedDataTable(
            id: $table->getId(),
            attributes: $table->getAttributes(),
            dataTableClass: $dataTableClass,
            dataSourceMode: $optIns->dataSourceMode,
            options: $options,
            extensions: $extensions,
            columns: $columns,
            configuredColumns: $configuredColumns,
            bundleOptions: array_filter([
                'pollInterval'   => $table->getBundleOption('pollInterval'),
                'urlState'       => $table->getBundleOption('urlState'),
                'styleFramework' => $table->getBundleOption('styleFramework'),
            ], static fn (mixed $value): bool => null !== $value),
            apiPlatform: $apiPlatform,
            apiPlatformServerSide: $apiPlatformServerSide,
            exportUrl: $table->getBundleOption('exportUrl') ?? $this->exportUrl($table),
            mercure: $mercure(),
            highlight: $table->getHighlightConfig()?->jsonSerialize(),
            bulkActions: null === $bulkActions ? null : [
                'actions'               => $bulkActions['actions'],
                'selectCurrentPageOnly' => $bulkActions['selectCurrentPageOnly'],
                // Client-side API Platform rows skip RowIdStage; the Stimulus adapter reads this
                // to stamp DT_RowId from the same field idField() / Doctrine remapping chose.
                'idField'  => $bulkRowIdField ?? $bulkActions['idField'],
                'url'      => $this->urlGenerator?->generate(self::AJAX_BULK_ROUTE),
                'labels'   => $this->translateLabels(self::BULK_LABEL_KEYS) ?? [],
                'position' => $bulkActions['position'],
            ],
            declaresBulkActions: $table->hasBulkActions(),
            rowActionUrls: $hasRowActions ? $this->rowActionUrls() : null,
            emptyState: null === $emptyState ? null : $emptyState + ['labels' => $this->translateLabels(self::EMPTY_STATE_LABEL_KEYS) ?? []],
            recordUrl: null !== $table->getRecordUrlResolver(),
            actionLabels: $hasRowActions ? $this->translateLabels(self::ACTION_LABEL_KEYS) : null,
            summaryLabels: $this->summaryLabels($columns),
            forwardedQuery: $forwardedQuery,
            filters: $hasFilters ? $filters->jsonSerialize() : null,
            showHeaderResetButton: $hasFilters && ($filters->isHeaderResetButtonVisible() ?? $table->getBundleOption('showHeaderResetButton') ?? false),
            filterLabels: $hasFilters ? $this->filterLabels($filters) : null,
            actionToken: null === $dataTableClass ? null : $this->ajaxRegistry?->getActionToken($dataTableClass),
            editModalTemplate: $optIns->editModalTemplate,
            editModalAdapter: $optIns->editModalAdapter,
            locale: $this->requestStack?->getCurrentRequest()?->getLocale(),
            csrfToken: $this->mutationToken(),
        );
    }

    /**
     * Translated copies of the columns, so the configured titles keep their translation keys.
     *
     * @param array<AbstractColumn> $columns
     *
     * @return list<AbstractColumn>
     */
    private function translatedColumns(array $columns): array
    {
        $columns = array_map(static fn (AbstractColumn $column): AbstractColumn => clone $column, array_values($columns));

        if (null !== $this->translator) {
            ColumnTranslator::translate($columns, $this->translator);
        }

        return $columns;
    }

    /**
     * A copy of the filters with their entity options loaded and their labels translated.
     */
    private function translatedFilters(?Filters $filters): ?Filters
    {
        if (null === $filters) {
            return null;
        }

        $filters = clone $filters;
        ($this->filterEntityOptionsResolver ?? new FilterEntityOptionsResolver())->prepare($filters);

        if (null !== $this->translator) {
            foreach ($filters->getFilters() as $filter) {
                $filter->translateLabels($this->translator);
            }
        }

        return $filters;
    }

    /**
     * The bundle's Ajax data route, carrying the table's read token.
     *
     * @return array{type: string, url: string, data: array<string, string>}|null
     */
    private function bundleAjax(?string $dataTableClass): ?array
    {
        if (null === $dataTableClass || null === $this->urlGenerator) {
            return null;
        }

        $token = $this->ajaxRegistry?->getToken($dataTableClass);
        if (null === $token) {
            return null;
        }

        return [
            'type' => 'GET',
            'url'  => $this->urlGenerator->generate(self::AJAX_DATA_ROUTE),
            'data' => ['table' => $token],
        ];
    }

    /**
     * Whether a column renders from the entity rather than from the row the browser holds.
     *
     * Templates are rendered by Twig, action metadata by the voters, the URL generator and the CSRF
     * token manager, and a resolved URL by a closure taking the entity. None of the three can be
     * produced from a normalized API response, so any one of them sends the whole table
     * server-side.
     */
    private function hasEntityDependentColumn(DataTable $table): bool
    {
        foreach ($table->getColumns() as $column) {
            if ($column instanceof TemplateAwareColumnInterface || $column instanceof ActionsProvidingColumnInterface) {
                return true;
            }

            if ($column instanceof UrlColumn && $column->hasUrlResolver()) {
                return true;
            }
        }

        return false;
    }

    private function exportUrl(DataTable $table): ?string
    {
        $buttons = $table->getExtensionsCollection()->getButtonsExtension();
        if (!$buttons instanceof ButtonsExtension || !$buttons->hasServerExportButton()) {
            return null;
        }

        $fqcn = $table->getDataTableClass();
        if (null === $fqcn || null === $this->urlGenerator || null === $this->ajaxRegistry) {
            return null;
        }

        $token = $this->ajaxRegistry->getToken($fqcn);
        if (null === $token) {
            return null;
        }

        return $this->urlGenerator->generate(self::AJAX_EXPORT_ROUTE, ['table' => $token]);
    }

    /**
     * Drop bulk actions the user may not run, without touching the container-shared collection.
     *
     * @return array{actions: array<mixed>, selectCurrentPageOnly: bool, idField: ?string, position: mixed}|null null when the user may run none
     */
    private function allowedBulkActions(?BulkActions $bulkActions, ?string $dataTableClass): ?array
    {
        if (null === $bulkActions || $bulkActions->isEmpty()) {
            return null;
        }

        $allowed = (clone $bulkActions)->filterStaticPermissions(
            $this->permissionChecker,
            $dataTableClass,
        );

        if ($allowed->isEmpty()) {
            return null;
        }

        return [
            'actions'               => $allowed->jsonSerialize(),
            'selectCurrentPageOnly' => $bulkActions->isSelectCurrentPageOnly(),
            'idField'               => $bulkActions->getIdField(),
            'position'              => $bulkActions->getPosition(),
        ];
    }

    /**
     * @return array<string, string>|null
     */
    private function rowActionUrls(): ?array
    {
        if (null === $this->urlGenerator) {
            return null;
        }

        $urls = [];
        foreach (self::ROW_ACTION_ROUTES as $name => $route) {
            try {
                $urls[$name] = $this->urlGenerator->generate($route);
            } catch (RouteNotFoundException) {
                // The route is not imported (or Form is absent): the frontend refuses that row action.
            }
        }

        return $urls;
    }

    /**
     * @param list<AbstractColumn> $columns
     *
     * @return array<string, string>|null
     */
    private function summaryLabels(array $columns): ?array
    {
        if (null === $this->translator || [] === ColumnResolver::filterSummarized($columns)) {
            return null;
        }

        $labels = [];
        foreach (SummaryType::cases() as $type) {
            $labels[$type->value] = $this->translator->trans('summary.'.$type->value, domain: FilterLabels::DOMAIN);
        }

        return $labels;
    }

    /**
     * @return array<string, string>|null
     */
    private function filterLabels(Filters $filters): ?array
    {
        if (null !== $this->translator) {
            return $filters->getLabels()->toTranslatedArray($this->translator);
        }

        return $filters->getLabels()->isEmpty() ? null : $filters->getLabels()->jsonSerialize();
    }

    /**
     * @param array<string, string> $keys
     *
     * @return array<string, string>|null null without a translator
     */
    private function translateLabels(array $keys): ?array
    {
        if (null === $this->translator) {
            return null;
        }

        return array_map(fn (string $key): string => $this->translator->trans($key, domain: FilterLabels::DOMAIN), $keys);
    }

    private function rendersActionUrls(DataTable $table): bool
    {
        foreach ($table->getColumns() as $column) {
            if ($column instanceof ActionsProvidingColumnInterface || ($column instanceof BooleanColumn && $column->isRenderedAsSwitch())) {
                return true;
            }
        }

        return false;
    }

    /**
     * The values of the forwarded query parameters present on the current request.
     *
     * @return array<string, mixed>|null
     */
    private function forwardedQuery(DataTable $table): ?array
    {
        $names = $table->getForwardedQueryParameters();
        if ([] === $names) {
            return null;
        }

        $request = $this->requestStack?->getCurrentRequest();
        if (null === $request) {
            return null;
        }

        $forwarded = array_intersect_key($request->query->all(), array_flip($names));

        return [] === $forwarded ? null : $forwarded;
    }

    private function mutationToken(): ?string
    {
        if (null === $this->csrfTokenManager) {
            return null;
        }

        try {
            return $this->csrfTokenManager->getToken(MutationTokenValidator::TOKEN_ID)->getValue();
        } catch (SessionNotFoundException) {
            return null;
        }
    }
}
