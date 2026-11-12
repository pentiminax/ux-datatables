<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Runtime;

use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\ApiPlatform\ApiResourceCollectionUrlResolver;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Column\ColumnTranslator;
use Pentiminax\UX\DataTables\Column\UrlColumn;
use Pentiminax\UX\DataTables\Contracts\ActionsProvidingColumnInterface;
use Pentiminax\UX\DataTables\Contracts\TemplateAwareColumnInterface;
use Pentiminax\UX\DataTables\Mercure\MercureConfig;
use Pentiminax\UX\DataTables\Mercure\MercureConfigResolver;
use Pentiminax\UX\DataTables\Mercure\MercureHubUrlResolver;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Extensions\ButtonsExtension;
use Pentiminax\UX\DataTables\Model\FilterLabels;
use Pentiminax\UX\DataTables\Model\Summary\SummaryType;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class RenderingPreparer
{
    public const AJAX_DATA_ROUTE   = 'ux_datatables_ajax_data';
    public const AJAX_EXPORT_ROUTE = 'ux_datatables_ajax_export';
    public const AJAX_BULK_ROUTE   = 'ux_datatables_ajax_bulk';

    private const ROW_ACTION_ROUTES = [
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
    private const BULK_LABEL_KEYS = [
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
    private const ACTION_LABEL_KEYS = [
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
    private const EMPTY_STATE_LABEL_KEYS = [
        'noResults'     => 'empty.noResults',
        'noResultsHint' => 'empty.noResultsHint',
        'resetFilters'  => 'empty.resetFilters',
    ];

    public function __construct(
        private readonly ?ApiResourceCollectionUrlResolver $urlResolver = null,
        private readonly ?MercureConfigResolver $mercureResolver = null,
        private readonly ?TranslatorInterface $translator = null,
        private readonly ?MercureHubUrlResolver $mercureHubUrlResolver = null,
        private readonly ?UrlGeneratorInterface $urlGenerator = null,
        private readonly ?AjaxDataTableRegistry $ajaxRegistry = null,
        private readonly ?RequestStack $requestStack = null,
        private readonly ?SearchListOptionsResolver $searchListOptionsResolver = null,
        private readonly ?FilterEntityOptionsResolver $filterEntityOptionsResolver = null,
        private readonly ?ColumnResolver $columnResolver = null,
        private readonly DataSourceFeatureGuard $featureGuard = new DataSourceFeatureGuard(),
    ) {
    }

    public function prepare(DataTable $table, ?AsDataTable $asDataTable, ?ResolvedOptIns $optIns = null): void
    {
        $optIns ??= ResolvedOptIns::resolve($table, $asDataTable);

        $this->prepareBeforeDataHydration($table, $asDataTable, $optIns);
        $this->prepareAfterDataHydration($table, $asDataTable, $optIns);
    }

    public function prepareBeforeDataHydration(DataTable $table, ?AsDataTable $asDataTable, ?ResolvedOptIns $optIns = null): void
    {
        $optIns ??= ResolvedOptIns::resolve($table, $asDataTable);

        $mode = $this->resolveDataSourceMode($table, $asDataTable, $optIns);
        $this->featureGuard->assertConfigurable($table, $mode);

        if (DataSourceMode::ApiPlatform === $mode) {
            $this->configureApiPlatform($table, $optIns);
        }

        if (DataSourceMode::Ajax === $mode && true === $asDataTable?->apiPlatform && null === $table->getOption('apiPlatform')) {
            trigger_deprecation('pentiminax/ux-datatables', '1.6', 'Table "%s" combines #[AsDataTable(apiPlatform: true)] with ajax() and renders plain DataTables JSON today, and 2.0 sends the API Platform flag for it (Hydra adapter). Add ->apiPlatform(false) to keep plain DataTables JSON, or ->apiPlatform() to opt in now.', $table->getDataTableClass() ?? $table->getId());
        }

        if (DataSourceMode::ServerSide === $mode) {
            $this->configureAutoAjax($table);
        }

        if (DataSourceMode::ApiPlatformUnavailable !== $mode) {
            $this->configureExportUrl($table);
        }

        $this->configureBulkActions($table);
        $this->configureRowActionUrls($table);
        $this->configureActionLabels($table);
        $this->configureSummaryLabels($table);
        $this->configureEmptyStateLabels($table);
        $this->configureForwardedQueryParameters($table);
        $this->configureEditModal($table, $optIns);
        $this->translateColumnTitles($table);
        $this->resolveFilterEntityOptions($table);
        $this->translateFilterLabels($table);
        ($this->searchListOptionsResolver ?? new SearchListOptionsResolver($this->translator))->prepare($table);
    }

    private function configureSummaryLabels(DataTable $table): void
    {
        $columns = $this->columnResolver?->filterStaticPermissions($table->getColumns(), $table->getDataTableClass()) ?? $table->getColumns();

        if (null === $this->translator || [] === ColumnResolver::filterSummarized($columns)) {
            return;
        }

        $labels = [];
        foreach (SummaryType::cases() as $type) {
            $labels[$type->value] = $this->translator->trans('summary.'.$type->value, domain: FilterLabels::DOMAIN);
        }

        $table->setPreparedSummaryLabels($labels);
    }

    private function resolveFilterEntityOptions(DataTable $table): void
    {
        ($this->filterEntityOptionsResolver ?? new FilterEntityOptionsResolver())->prepare($table);
    }

    public function prepareAfterDataHydration(DataTable $table, ?AsDataTable $asDataTable, ?ResolvedOptIns $optIns = null): void
    {
        $optIns ??= ResolvedOptIns::resolve($table, $asDataTable);

        $this->assertPollingHasAjaxSource($table);
        $this->configureMercure($table, $asDataTable, $optIns);
        $this->withdrawUnreadApiPlatformOptIn($table, $asDataTable, $optIns);
    }

    private function assertPollingHasAjaxSource(DataTable $table): void
    {
        if (null === $table->getOption('pollInterval') || null !== $table->getOption('ajax')) {
            return;
        }

        throw new \LogicException(\sprintf('Polling requires an Ajax source, but "%s" has no ajax option once prepared.', $table->getId()));
    }

    /**
     * @internal
     */
    public function resolveDataSourceMode(DataTable $table, ?AsDataTable $asDataTable, ?ResolvedOptIns $optIns = null): DataSourceMode
    {
        return DataSourceMode::resolve($table, $asDataTable, $this->urlResolver, $optIns);
    }

    private function configureApiPlatform(DataTable $table, ResolvedOptIns $optIns): void
    {
        $collectionUrl = null === $optIns->entityClass ? null : $this->urlResolver?->resolveCollectionUrl($optIns->entityClass);

        if (null === $collectionUrl) {
            return;
        }

        $table->apiPlatform();

        // A template, action or resolved URL column renders from the source entity, which the
        // browser never holds. Those tables read the collection server-side, through the same
        // operation, and the Stimulus adapter stays out of the way.
        if ($this->configureApiPlatformServerSide($table)) {
            return;
        }

        $table->ajax($collectionUrl);
    }

    /**
     * Withdraws a flag the browser cannot honor, whichever surface declared it: without a collection
     * URL, or over rows embedded in the page. Last, so the table never reads its rows around the
     * collection's authorization.
     */
    private function withdrawUnreadApiPlatformOptIn(DataTable $table, ?AsDataTable $asDataTable, ResolvedOptIns $optIns): void
    {
        if (!$optIns->apiPlatform) {
            return;
        }

        $mode = $this->resolveDataSourceMode($table, $asDataTable, $optIns);

        if (DataSourceMode::ApiPlatformUnavailable === $mode) {
            trigger_deprecation('pentiminax/ux-datatables', '1.6', 'Table "%s" opts into API Platform but "%s" exposes no collection operation, or API Platform is not installed: it reads no rows, and 2.0 throws a LogicException. Expose a collection operation or remove the apiPlatform opt-in.', $table->getDataTableClass() ?? $table->getId(), $optIns->entityClass ?? 'no entity class');
        }

        if (DataSourceMode::Inline === $mode) {
            trigger_deprecation('pentiminax/ux-datatables', '1.6', 'Table "%s" combines data() with apiPlatform(), which read rows from two places: the flag is now withdrawn from the payload, and 2.0 throws a LogicException. Drop one of them.', $table->getDataTableClass() ?? $table->getId());
        }

        if (true === $table->getOption('apiPlatform') && \in_array($mode, [DataSourceMode::ApiPlatformUnavailable, DataSourceMode::Inline], true)) {
            $table->apiPlatform(false);
        }
    }

    /**
     * @return bool whether the table now reads its collection through the bundle's Ajax endpoint
     */
    private function configureApiPlatformServerSide(DataTable $table): bool
    {
        if (!$this->hasEntityDependentColumn($table)) {
            return false;
        }

        $fqcn = $table->getDataTableClass();
        if (null === $fqcn || null === $this->urlGenerator || null === $this->ajaxRegistry) {
            return false;
        }

        $token = $this->ajaxRegistry->getToken($fqcn);
        if (null === $token) {
            return false;
        }

        $table->markApiPlatformServerSide();
        // The Stimulus adapter used to force serverSide on the payload; nothing does it now.
        $table->serverSide();
        $table->ajaxRequestData(
            url: $this->urlGenerator->generate(self::AJAX_DATA_ROUTE),
            data: ['table' => $token],
            type: 'GET',
        );

        return true;
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

    private function configureAutoAjax(DataTable $table): void
    {
        $fqcn = $table->getDataTableClass();
        if (null === $fqcn || null === $this->urlGenerator) {
            return;
        }

        $token = $this->ajaxRegistry?->getToken($fqcn);
        if (null === $token) {
            return;
        }

        $url = $this->urlGenerator->generate(self::AJAX_DATA_ROUTE);

        $table->ajaxRequestData(
            url: $url,
            data: ['table' => $token],
            type: 'GET',
        );
    }

    private function configureExportUrl(DataTable $table): void
    {
        if (null !== $table->getOption('exportUrl')) {
            return;
        }

        $buttons = $table->getExtensionsCollection()->getButtonsExtension();
        if (!$buttons instanceof ButtonsExtension || !$buttons->hasServerExportButton()) {
            return;
        }

        $fqcn = $table->getDataTableClass();
        if (null === $fqcn || null === $this->urlGenerator || null === $this->ajaxRegistry) {
            return;
        }

        $token = $this->ajaxRegistry->getToken($fqcn);
        if (null === $token) {
            return;
        }

        $table->exportUrl($this->urlGenerator->generate(self::AJAX_EXPORT_ROUTE, ['table' => $token]));
    }

    private function configureBulkActions(DataTable $table): void
    {
        if (!$table->hasBulkActions()) {
            return;
        }

        if (null !== $this->urlGenerator) {
            $table->setBulkActionsUrl($this->urlGenerator->generate(self::AJAX_BULK_ROUTE));
        }

        if (null === $this->translator) {
            return;
        }

        $labels = [];
        foreach (self::BULK_LABEL_KEYS as $name => $key) {
            $labels[$name] = $this->translator->trans($key, domain: FilterLabels::DOMAIN);
        }

        $table->setPreparedBulkActionLabels($labels);
    }

    private function configureRowActionUrls(DataTable $table): void
    {
        if (null === $this->urlGenerator || !$this->hasRowActions($table)) {
            return;
        }

        $urls = [];
        foreach (self::ROW_ACTION_ROUTES as $name => $route) {
            try {
                $urls[$name] = $this->urlGenerator->generate($route);
            } catch (RouteNotFoundException) {
                // The route is not imported (or Form is absent): the frontend falls back to its default path.
            }
        }

        $table->setRowActionUrls($urls);
    }

    private function configureActionLabels(DataTable $table): void
    {
        if (null === $this->translator || !$this->hasRowActions($table)) {
            return;
        }

        $labels = [];
        foreach (self::ACTION_LABEL_KEYS as $name => $key) {
            $labels[$name] = $this->translator->trans($key, domain: FilterLabels::DOMAIN);
        }

        $table->setPreparedActionLabels($labels);
    }

    private function configureEmptyStateLabels(DataTable $table): void
    {
        if (null === $this->translator || !$table->hasEmptyState()) {
            return;
        }

        $labels = [];
        foreach (self::EMPTY_STATE_LABEL_KEYS as $name => $key) {
            $labels[$name] = $this->translator->trans($key, domain: FilterLabels::DOMAIN);
        }

        $table->setPreparedEmptyStateLabels($labels);
    }

    private function hasRowActions(DataTable $table): bool
    {
        foreach ($table->getColumns() as $column) {
            if ($column instanceof ActionsProvidingColumnInterface || ($column instanceof BooleanColumn && $column->isRenderedAsSwitch())) {
                return true;
            }
        }

        return false;
    }

    private function configureForwardedQueryParameters(DataTable $table): void
    {
        $names = $table->getForwardedQueryParameters();
        if ([] === $names) {
            return;
        }

        $request = $this->requestStack?->getCurrentRequest();
        if (null === $request) {
            return;
        }

        $all       = $request->query->all();
        $forwarded = [];
        foreach ($names as $name) {
            if (\array_key_exists($name, $all)) {
                $forwarded[$name] = $all[$name];
            }
        }

        if ([] === $forwarded) {
            return;
        }

        $table->mergeAjaxData($forwarded);
        $table->setPreparedForwardedQuery($forwarded);
    }

    private function configureMercure(DataTable $table, ?AsDataTable $asDataTable, ResolvedOptIns $optIns): void
    {
        $config = $this->resolveMercureConfig($table, $asDataTable, $optIns);

        if (null !== $config) {
            $table->setMercureConfig($config);
        }
    }

    /**
     * Resolve the Mercure configuration a table serializes to the browser,
     * WITHOUT mutating the table.
     *
     * Single source of truth for the topic precedence — manual ->mercure() >
     * explicit #[AsDataTable(mercure: [...])] > entity-class auto-resolver —
     * so the server-side publish path can reuse it and always publish to the
     * exact topics the client subscribed to. Returns null when the table
     * exposes no live Mercure config.
     */
    public function resolveMercureConfig(DataTable $table, ?AsDataTable $asDataTable, ?ResolvedOptIns $optIns = null): ?MercureConfig
    {
        $optIns ??= ResolvedOptIns::resolve($table, $asDataTable);
        $mercure = $optIns->mercure;

        if ($mercure instanceof MercureConfig) {
            return $mercure
                ->withHubUrl($this->resolveHubUrlOrThrow())
                ->withProtocolVersion($this->resolveProtocolVersion());
        }

        if (false === $mercure || null === $optIns->entityClass) {
            return null;
        }

        if (DataSourceMode::Inline === $this->resolveDataSourceMode($table, $asDataTable, $optIns)) {
            return null;
        }

        if (\is_array($mercure)) {
            return $this->createExplicitMercureConfig($mercure, $optIns->entityClass);
        }

        return $this->mercureResolver?->resolveMercureConfig($optIns->entityClass);
    }

    /**
     * Build the config declared on the attribute.
     *
     * Without a `topics` key the array only carries subscription options, so topics come from the
     * auto-resolver and the declared options are applied on top of it.
     *
     * @param array<string, mixed> $mercure
     */
    private function createExplicitMercureConfig(array $mercure, string $entityClass): ?MercureConfig
    {
        $this->assertKnownMercureOptions($mercure, $entityClass);

        $debounceMs = $mercure['debounceMs'] ?? null;
        if (null !== $debounceMs && !\is_int($debounceMs)) {
            throw new \InvalidArgumentException(\sprintf('The mercure "debounceMs" option declared on #[AsDataTable] for "%s" must be an integer or null.', $entityClass));
        }

        $withCredentials = $mercure['withCredentials'] ?? null;
        if (null !== $withCredentials && !\is_bool($withCredentials)) {
            throw new \InvalidArgumentException(\sprintf('The mercure "withCredentials" option declared on #[AsDataTable] for "%s" must be a boolean.', $entityClass));
        }

        if (!\array_key_exists('topics', $mercure)) {
            $resolved = $this->mercureResolver?->resolveMercureConfig($entityClass);

            if (null === $resolved) {
                return null;
            }

            return new MercureConfig(
                topics: $resolved->topics,
                withCredentials: $withCredentials ?? $resolved->withCredentials,
                debounceMs: $debounceMs,
                hubUrl: $resolved->hubUrl,
                protocolVersion: $resolved->protocolVersion,
            );
        }

        $topics = $mercure['topics'];
        if (\is_string($topics)) {
            $topics = [$topics];
        }

        if (!\is_array($topics)) {
            throw new \InvalidArgumentException(\sprintf('The mercure "topics" option declared on #[AsDataTable] for "%s" must be a string or an array of strings.', $entityClass));
        }

        return (new MercureConfig(
            topics: $topics,
            withCredentials: $withCredentials ?? false,
            debounceMs: $debounceMs,
        ))
            ->withHubUrl($this->resolveHubUrlOrThrow())
            ->withProtocolVersion($this->resolveProtocolVersion());
    }

    /**
     * @param array<string, mixed> $mercure
     */
    private function assertKnownMercureOptions(array $mercure, string $entityClass): void
    {
        $unknown = array_diff(array_keys($mercure), ['topics', 'withCredentials', 'debounceMs']);

        if ([] === $unknown) {
            return;
        }

        throw new \InvalidArgumentException(\sprintf('Unknown mercure option(s) "%s" declared on #[AsDataTable] for "%s". Supported options are "topics", "withCredentials" and "debounceMs".', implode('", "', $unknown), $entityClass));
    }

    private function resolveHubUrlOrThrow(): string
    {
        $hubUrl = $this->mercureHubUrlResolver?->resolveHubUrl();

        if (null === $hubUrl || '' === $hubUrl) {
            throw new \LogicException('Cannot enable Mercure on this DataTable: the Mercure hub URL could not be resolved. Ensure symfony/mercure-bundle is installed and configured (e.g. MERCURE_URL / MERCURE_PUBLIC_URL).');
        }

        return $hubUrl;
    }

    private function resolveProtocolVersion(): string
    {
        return $this->mercureHubUrlResolver?->resolveProtocolVersion() ?? MercureConfig::PROTOCOL_VERSION_0_X;
    }

    private function configureEditModal(DataTable $table, ResolvedOptIns $optIns): void
    {
        if (null !== $optIns->editModalAdapter) {
            $table->editModalAdapter($optIns->editModalAdapter);
        }

        if (null !== $optIns->editModalTemplate) {
            $table->editModalTemplate($optIns->editModalTemplate);
        }
    }

    private function translateColumnTitles(DataTable $table): void
    {
        if (null === $this->translator) {
            return;
        }

        ColumnTranslator::translate($table->getColumns(), $this->translator);
    }

    private function translateFilterLabels(DataTable $table): void
    {
        if (null === $this->translator) {
            return;
        }

        $filters = $table->getFilters();
        if (null === $filters) {
            return;
        }

        foreach ($filters->getFilters() as $filter) {
            $filter->translateLabels($this->translator);
        }

        $table->setPreparedFilterLabels(
            $filters->getLabels()->toTranslatedArray($this->translator)
        );
    }
}
