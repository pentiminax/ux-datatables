<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Rendering;

use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Contracts\ApiResourceCollectionUrlResolverInterface;
use Pentiminax\UX\DataTables\Contracts\MercureConfigResolverInterface;
use Pentiminax\UX\DataTables\Contracts\MercureHubUrlResolverInterface;
use Pentiminax\UX\DataTables\Model\DataTable;
use Symfony\Contracts\Translation\TranslatorInterface;

final class RenderingPreparer
{
    public function __construct(
        private readonly ?ApiResourceCollectionUrlResolverInterface $urlResolver = null,
        private readonly ?MercureConfigResolverInterface $mercureResolver = null,
        private readonly ?TranslatorInterface $translator = null,
        private readonly ?MercureHubUrlResolverInterface $mercureHubUrlResolver = null,
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
        $this->configureEditModal($table, $asDataTable);
        $this->translateColumnTitles($table);
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

    private function configureMercure(DataTable $table, ?AsDataTable $asDataTable): void
    {
        $manualConfig = $table->getMercureConfig();

        if (null !== $manualConfig) {
            $table->setMercureConfig(
                $manualConfig->withHubUrl($this->resolveHubUrlOrThrow())
            );

            return;
        }

        if (null === $asDataTable || !$asDataTable->mercure) {
            return;
        }

        if (null !== $table->getOption('data') && null === $table->getOption('ajax')) {
            return;
        }

        if (null === $this->mercureResolver) {
            return;
        }

        $mercureConfig = $this->mercureResolver->resolveMercureConfig($asDataTable->entityClass);
        if (null === $mercureConfig) {
            return;
        }

        $table->setMercureConfig($mercureConfig);
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
}
