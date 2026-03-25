<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Rendering;

use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Contracts\ApiResourceCollectionUrlResolverInterface;
use Pentiminax\UX\DataTables\Contracts\MercureConfigResolverInterface;
use Pentiminax\UX\DataTables\Model\DataTable;
use Symfony\Contracts\Translation\TranslatorInterface;

final class RenderingPreparer
{
    public function __construct(
        private readonly ?ApiResourceCollectionUrlResolverInterface $urlResolver = null,
        private readonly ?MercureConfigResolverInterface $mercureResolver = null,
        private readonly ?TranslatorInterface $translator = null,
    ) {
    }

    public function prepare(DataTable $table, ?AsDataTable $asDataTable): void
    {
        $this->configureApiPlatform($table, $asDataTable);
        $this->configureMercure($table, $asDataTable);
        $this->translateColumnTitles($table);
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
        if (null !== $table->getMercureConfig()) {
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

        $table->mercure(
            hubUrl: $mercureConfig->hubUrl,
            topics: $mercureConfig->topics,
            withCredentials: $mercureConfig->withCredentials,
            debounceMs: $mercureConfig->debounceMs,
        );
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
