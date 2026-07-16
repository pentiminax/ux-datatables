<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Rendering;

use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\ApiPlatform\ApiResourceCollectionUrlResolverInterface;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Contracts\TemplateAwareColumnInterface;
use Pentiminax\UX\DataTables\Contracts\TranslatableFilterInterface;
use Pentiminax\UX\DataTables\Mercure\MercureConfig;
use Pentiminax\UX\DataTables\Mercure\MercureConfigResolverInterface;
use Pentiminax\UX\DataTables\Mercure\MercureHubUrlResolverInterface;
use Pentiminax\UX\DataTables\Model\DataTable;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class RenderingPreparer
{
    public const AJAX_DATA_ROUTE = 'ux_datatables_ajax_data';

    public function __construct(
        private readonly ?ApiResourceCollectionUrlResolverInterface $urlResolver = null,
        private readonly ?MercureConfigResolverInterface $mercureResolver = null,
        private readonly ?TranslatorInterface $translator = null,
        private readonly ?MercureHubUrlResolverInterface $mercureHubUrlResolver = null,
        private readonly ?UrlGeneratorInterface $urlGenerator = null,
        private readonly ?AjaxDataTableRegistry $ajaxRegistry = null,
        private readonly ?RequestStack $requestStack = null,
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
        $this->configureApiPlatformTemplateRendering($table);
        $this->configureAutoAjax($table);
        $this->configureForwardedQueryParameters($table);
        $this->configureEditModal($table, $asDataTable);
        $this->translateColumnTitles($table);
        $this->translateFilterLabels($table);
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

        if (null !== $collectionUrl) {
            $table->ajax($collectionUrl);
            $table->apiPlatform();
        }
    }

    private function canAutoWireApiPlatform(DataTable $table, ?AsDataTable $asDataTable): bool
    {
        return null === $table->getOption('ajax')
            && null === $table->getOption('data')
            && null !== $this->urlResolver
            && null !== $asDataTable
            && ($asDataTable->apiPlatform || $table->getOption('apiPlatform'));
    }

    private function configureApiPlatformTemplateRendering(DataTable $table): void
    {
        if (true !== $table->getOption('apiPlatform')) {
            return;
        }

        if (null !== $table->getOption('apiPlatformTemplateRendering')) {
            return;
        }

        if (null === $this->urlGenerator || null === $this->ajaxRegistry) {
            return;
        }

        if (!$this->hasTemplateColumn($table)) {
            return;
        }

        $fqcn = $table->getDataTableClass();
        if (null === $fqcn) {
            return;
        }

        $token = $this->ajaxRegistry->getToken($fqcn);
        if (null === $token) {
            return;
        }

        $table->apiPlatformTemplateRendering(
            url: $this->urlGenerator->generate('ux_datatables_ajax_templates'),
            tableToken: $token,
        );
    }

    private function hasTemplateColumn(DataTable $table): bool
    {
        foreach ($table->getColumns() as $column) {
            if ($column instanceof TemplateAwareColumnInterface) {
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
            return $manualConfig->withHubUrl($this->resolveHubUrlOrThrow());
        }

        if (null === $asDataTable || false === $asDataTable->mercure) {
            return null;
        }

        if (null !== $table->getOption('data') && null === $table->getOption('ajax')) {
            return null;
        }

        $explicitConfig = $this->createExplicitMercureConfig($asDataTable);
        if (null !== $explicitConfig) {
            return $explicitConfig;
        }

        return $this->mercureResolver?->resolveMercureConfig($asDataTable->entityClass);
    }

    private function createExplicitMercureConfig(AsDataTable $asDataTable): ?MercureConfig
    {
        if (!\is_array($asDataTable->mercure)) {
            return null;
        }

        $topics = $asDataTable->mercure['topics'] ?? [];
        if (\is_string($topics)) {
            $topics = [$topics];
        }

        if (!\is_array($topics)) {
            throw new \InvalidArgumentException('AsDataTable mercure topics must be a string or an array of strings.');
        }

        $debounceMs = $asDataTable->mercure['debounceMs'] ?? null;
        if (null !== $debounceMs && !\is_int($debounceMs)) {
            throw new \InvalidArgumentException('AsDataTable mercure debounceMs must be an integer or null.');
        }

        return (new MercureConfig(
            topics: $topics,
            withCredentials: true === ($asDataTable->mercure['withCredentials'] ?? false),
            debounceMs: $debounceMs,
        ))->withHubUrl($this->resolveHubUrlOrThrow());
    }

    private function resolveHubUrlOrThrow(): string
    {
        $hubUrl = $this->mercureHubUrlResolver?->resolveHubUrl();

        if (null === $hubUrl || '' === $hubUrl) {
            throw new \LogicException('Cannot enable Mercure on this DataTable: the Mercure hub URL could not be resolved. Ensure symfony/mercure-bundle is installed and configured (e.g. MERCURE_URL / MERCURE_PUBLIC_URL).');
        }

        return $hubUrl;
    }

    private function configureEditModal(DataTable $table, ?AsDataTable $asDataTable): void
    {
        if (null === $asDataTable) {
            return;
        }

        if (null === $table->getEditModalAdapter() && '' !== trim($asDataTable->editModalAdapter)) {
            $table->editModalAdapter($asDataTable->editModalAdapter);
        }

        if (null === $table->getEditModalTemplate() && '' !== trim($asDataTable->editModalTemplate)) {
            $table->editModalTemplate($asDataTable->editModalTemplate);
        }
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
            if ($filter instanceof TranslatableFilterInterface) {
                $filter->translateLabels($this->translator);
            }
        }

        $table->setPreparedFilterLabels(
            $filters->getLabels()->toTranslatedArray($this->translator)
        );
    }
}
