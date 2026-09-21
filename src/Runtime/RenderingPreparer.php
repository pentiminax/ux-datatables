<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Runtime;

use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\ApiPlatform\ApiResourceCollectionUrlResolver;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\UrlColumn;
use Pentiminax\UX\DataTables\Contracts\ActionsProvidingColumnInterface;
use Pentiminax\UX\DataTables\Contracts\TemplateAwareColumnInterface;
use Pentiminax\UX\DataTables\Mercure\MercureConfig;
use Pentiminax\UX\DataTables\Mercure\MercureConfigResolver;
use Pentiminax\UX\DataTables\Mercure\MercureHubUrlResolver;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Extensions\ButtonsExtension;
use Pentiminax\UX\DataTables\Model\FilterLabels;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class RenderingPreparer
{
    public const AJAX_DATA_ROUTE   = 'ux_datatables_ajax_data';
    public const AJAX_EXPORT_ROUTE = 'ux_datatables_ajax_export';
    public const AJAX_BULK_ROUTE   = 'ux_datatables_ajax_bulk';

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
    ) {
    }

    public function prepare(DataTable $table, ?AsDataTable $asDataTable): void
    {
        $this->prepareBeforeDataHydration($table, $asDataTable);
        $this->prepareAfterDataHydration($table, $asDataTable);
    }

    public function prepareBeforeDataHydration(DataTable $table, ?AsDataTable $asDataTable): void
    {
        $this->configureApiPlatform($table, $asDataTable);
        $this->configureAutoAjax($table);
        $this->configureExportUrl($table);
        $this->configureBulkActions($table);
        $this->configureForwardedQueryParameters($table);
        $this->configureEditModal($table, $asDataTable);
        $this->translateColumnTitles($table);
        $this->translateFilterLabels($table);
        ($this->searchListOptionsResolver ?? new SearchListOptionsResolver($this->translator))->prepare($table);
    }

    public function prepareAfterDataHydration(DataTable $table, ?AsDataTable $asDataTable): void
    {
        $this->configureMercure($table, $asDataTable);
    }

    private function configureApiPlatform(DataTable $table, ?AsDataTable $asDataTable): void
    {
        if (!$this->canAutoWireApiPlatform($table, $asDataTable)) {
            return;
        }

        $collectionUrl = $this->urlResolver->resolveCollectionUrl($asDataTable->entityClass);

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

        $table->apiPlatformServerSide();
        // The Stimulus adapter used to force serverSide on the payload; nothing does it now.
        $table->serverSide();
        $table->ajaxRequestData(
            url: $this->urlGenerator->generate(self::AJAX_DATA_ROUTE),
            data: ['table' => $token],
            type: 'GET',
        );

        return true;
    }

    private function canAutoWireApiPlatform(DataTable $table, ?AsDataTable $asDataTable): bool
    {
        return null === $table->getOption('ajax')
            && null === $table->getOption('data')
            && null !== $this->urlResolver
            && null !== $asDataTable
            && ($asDataTable->apiPlatform || $table->getOption('apiPlatform'));
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
        if (!$this->canAutoWireAjax($table)) {
            return;
        }

        $fqcn = $table->getDataTableClass();
        if (null === $fqcn) {
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

    private function canAutoWireAjax(DataTable $table): bool
    {
        return $table->isServerSide()
            && null === $table->getOption('ajax')
            && null === $table->getOption('data')
            && true !== $table->getOption('apiPlatform')
            && null !== $this->urlGenerator
            && null !== $this->ajaxRegistry;
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
    }

    private function configureMercure(DataTable $table, ?AsDataTable $asDataTable): void
    {
        $config = $this->resolveMercureConfig($table, $asDataTable);

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
    public function resolveMercureConfig(DataTable $table, ?AsDataTable $asDataTable): ?MercureConfig
    {
        $manualConfig = $table->getMercureConfig();
        if (null !== $manualConfig) {
            return $manualConfig
                ->withHubUrl($this->resolveHubUrlOrThrow())
                ->withProtocolVersion($this->resolveProtocolVersion());
        }

        if (null === $asDataTable || false === $asDataTable->mercure) {
            return null;
        }

        if (null !== $table->getOption('data') && null === $table->getOption('ajax')) {
            return null;
        }

        if (\is_array($asDataTable->mercure)) {
            return $this->createExplicitMercureConfig($asDataTable->mercure, $asDataTable->entityClass);
        }

        return $this->mercureResolver?->resolveMercureConfig($asDataTable->entityClass);
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

    private function configureEditModal(DataTable $table, ?AsDataTable $asDataTable): void
    {
        if (null === $asDataTable) {
            return;
        }

        if (null === $table->getEditModalAdapter() && $this->hasOverride($asDataTable->editModalAdapter)) {
            $table->editModalAdapter($asDataTable->editModalAdapter);
        }

        if (null === $table->getEditModalTemplate() && $this->hasOverride($asDataTable->editModalTemplate)) {
            $table->editModalTemplate($asDataTable->editModalTemplate);
        }
    }

    private function hasOverride(?string $value): bool
    {
        return null !== $value && '' !== trim($value);
    }

    private function translateColumnTitles(DataTable $table): void
    {
        if (null === $this->translator) {
            return;
        }

        foreach ($table->getColumns() as $column) {
            $title = $column->getTitle();
            $column->setTitle($this->translator->trans($title));
        }
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
