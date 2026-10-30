<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Runtime;

use Pentiminax\UX\DataTables\Column\AbstractColumn;
use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\FeatureAwareDataProviderInterface;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Enum\DataSourceFeature;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Extensions\ColumnControlExtension;
use Pentiminax\UX\DataTables\Model\Extensions\SearchBuilderExtension;
use Pentiminax\UX\DataTables\Model\Filters;
use Pentiminax\UX\DataTables\Query\Intent\DefaultDataTableQueryIntentFactory;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Refuses a data source feature the table's data source cannot apply, instead of answering
 * unfiltered.
 *
 * @internal
 */
final class DataSourceFeatureGuard
{
    /** Features an API Platform collection cannot carry, whether the browser or the server reads it. */
    private const array API_PLATFORM_UNSUPPORTED = [
        DataSourceFeature::SearchBuilder,
        DataSourceFeature::ColumnControl,
        DataSourceFeature::Summaries,
    ];

    public function __construct(
        private readonly DefaultDataTableQueryIntentFactory $intentFactory = new DefaultDataTableQueryIntentFactory(),
    ) {
    }

    /**
     * Render-time check, from the mode alone: no provider is built while rendering, so a server-side
     * table is checked on its first request instead.
     *
     * @throws \LogicException when the table configures a feature its mode cannot apply
     */
    public function assertConfigurable(DataTable $table, DataSourceMode $mode): void
    {
        if (DataSourceMode::ClientHydrated === $mode || DataSourceMode::Inline === $mode) {
            $this->assertBrowserCanSummarize($table);

            return;
        }

        if (DataSourceMode::ApiPlatform !== $mode) {
            return;
        }

        foreach (self::API_PLATFORM_UNSUPPORTED as $feature) {
            if ($this->configures($table, $feature)) {
                throw new \LogicException(\sprintf('%s are not supported on an API Platform collection, because it cannot apply them to every matching row. Remove them or read the table through Doctrine.', $feature->label()));
            }
        }
    }

    /**
     * @param list<ColumnInterface> $columns the table's configured columns
     *
     * @throws BadRequestHttpException when the request carries criteria the provider cannot apply
     * @throws \LogicException         when the table declares summaries the provider cannot compute
     */
    public function assertRequestSupported(DataTableRequest $request, DataProviderInterface $provider, DataTable $table, array $columns): void
    {
        if (!$provider instanceof FeatureAwareDataProviderInterface) {
            return;
        }

        foreach (DataSourceFeature::cases() as $feature) {
            if ($provider->supports($feature)) {
                continue;
            }

            if (DataSourceFeature::Summaries === $feature) {
                if ([] !== ColumnResolver::filterSummarized($columns)) {
                    throw new \LogicException(\sprintf('Column summaries are not supported by %s. Remove summarize() or use a data provider that computes them.', $this->providerName($provider)));
                }

                continue;
            }

            if ($this->requests($request, $feature, $table, $columns)) {
                throw new BadRequestHttpException(\sprintf('%s are not supported by %s. Use a DoctrineDataProvider or a custom DataProviderInterface for this table.', $feature->label(), $this->providerName($provider)));
            }
        }
    }

    /**
     * The browser aggregates the cell values it holds, which formatValueUsing() has already turned
     * into text: "1,234.50" is not a number.
     */
    private function assertBrowserCanSummarize(DataTable $table): void
    {
        foreach (ColumnResolver::filterSummarized($table->getColumns()) as $column) {
            if (null === $column->getValueFormatter()) {
                continue;
            }

            foreach ($column->getSummarizers() as $summarizer) {
                if ($summarizer->getType()->requiresNumericColumn()) {
                    throw new \LogicException(\sprintf('The "%s" summary of column "%s" cannot be computed in the browser, because formatValueUsing() replaces the numbers it would aggregate. Make the table server-side, drop formatValueUsing(), or summarize with Count only.', $summarizer->getType()->value, $column->getName()));
                }
            }
        }
    }

    private function configures(DataTable $table, DataSourceFeature $feature): bool
    {
        $extensions = $table->getExtensionsCollection()->all();

        return match ($feature) {
            DataSourceFeature::SearchBuilder     => ($extensions['searchBuilder'] ?? null) instanceof SearchBuilderExtension,
            DataSourceFeature::ColumnControl     => ($extensions['columnControl'] ?? null) instanceof ColumnControlExtension && $this->offersColumnControlSearch($extensions['columnControl'], $table->getColumns()),
            DataSourceFeature::ConfiguredFilters => [] !== ($table->getFilters()?->getFilters() ?? []),
            DataSourceFeature::Summaries         => [] !== ColumnResolver::filterSummarized($table->getColumns()),
        };
    }

    /**
     * @param list<ColumnInterface> $columns
     */
    private function requests(DataTableRequest $request, DataSourceFeature $feature, DataTable $table, array $columns): bool
    {
        return match ($feature) {
            DataSourceFeature::SearchBuilder     => null !== $request->searchBuilder,
            DataSourceFeature::ColumnControl     => []   !== $this->intentFactory->create($request, $columns)->columnControls,
            DataSourceFeature::ConfiguredFilters => $this->hasActiveFilters($request, $table->getFilters()),
            DataSourceFeature::Summaries         => false,
        };
    }

    /**
     * Only configured filter names count, and emptiness is tested exactly as
     * {@see \Pentiminax\UX\DataTables\Query\Builder\QueryFilterPipeline} tests it: an unknown
     * key is ignored, not rejected.
     */
    private function hasActiveFilters(DataTableRequest $request, ?Filters $filters): bool
    {
        foreach ($filters?->getFilters() ?? [] as $filter) {
            $value = $request->filters[$filter->getName()] ?? null;

            if (null !== $value && '' !== $value && [] !== $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<mixed> $columns
     */
    private function offersColumnControlSearch(ColumnControlExtension $extension, array $columns): bool
    {
        if ($this->containsSearchControl($extension->jsonSerialize())) {
            return true;
        }

        foreach ($columns as $column) {
            if ($column instanceof AbstractColumn && $this->containsSearchControl($column->getColumnControl())) {
                return true;
            }
        }

        return false;
    }

    /**
     * Content descriptors nest (a group or a dropdown holds a `content` list) and come as names,
     * `extend` arrays, or objects such as SearchList that serialize to one. Every ColumnControl search
     * type starts with "search"; `searchClear` only clears one, and other keys (icon, text) are
     * labels.
     */
    private function containsSearchControl(mixed $content): bool
    {
        if ($content instanceof \JsonSerializable) {
            return $this->containsSearchControl($content->jsonSerialize());
        }

        if (\is_string($content)) {
            return str_starts_with($content, 'search') && 'searchClear' !== $content;
        }

        if (!\is_array($content)) {
            return false;
        }

        if (!array_is_list($content)) {
            return $this->containsSearchControl($content['extend'] ?? null)
                || $this->containsSearchControl($content['content'] ?? null);
        }

        foreach ($content as $item) {
            if ($this->containsSearchControl($item)) {
                return true;
            }
        }

        return false;
    }

    private function providerName(DataProviderInterface $provider): string
    {
        $class = $provider::class;
        $pos   = strrpos($class, '\\');

        return false === $pos ? $class : substr($class, $pos + 1);
    }
}
