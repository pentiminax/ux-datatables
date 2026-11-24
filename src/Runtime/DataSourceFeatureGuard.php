<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Runtime;

use Pentiminax\UX\DataTables\Column\AbstractColumn;
use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\DataProvider\DoctrineDataProvider;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Enum\DataSourceFeature;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Filters;
use Pentiminax\UX\DataTables\Query\Filter\SearchBuilderFilter;
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
    /** Row scope and identifier collection are probed by DataSourceCapability, never through the match arms below. */
    private const QUERY_FEATURES = [
        DataSourceFeature::SearchBuilder,
        DataSourceFeature::ColumnControl,
        DataSourceFeature::ConfiguredFilters,
        DataSourceFeature::Summaries,
    ];

    public function __construct(
        private readonly DefaultDataTableQueryIntentFactory $intentFactory = new DefaultDataTableQueryIntentFactory(),
    ) {
    }

    /**
     * Configure-time check of a table's requirements against its data source's capability.
     *
     * Without a provider it reads the capability the mode alone gives and checks the query
     * features, which builds nothing. With one it checks the row scope only: query features are
     * refused per request by {@see self::assertRequestSupported()}.
     *
     * @throws \LogicException when the table requires what its data source cannot apply
     */
    public function assertSatisfied(TableDefinition $definition, ?DataProviderInterface $provider = null): void
    {
        if (null === $provider) {
            $this->assertModeSatisfies($definition);

            return;
        }

        if (!$definition->requirements->has(DataSourceFeature::RowScope) || null !== DataSourceCapability::rowScopeChecker($provider)) {
            return;
        }

        $table = $definition->table;

        throw new \LogicException(\sprintf('Table "%s" has row actions or bulk actions, but its data provider %s cannot check the row scope. Call unscopedMutations() on the table when the scope is enforced elsewhere, or implement IdentifierDataProviderInterface and declare DataSourceFeature::RowScope in supports().', $table->getDataTableClass() ?? $table->getId(), self::providerName($provider)));
    }

    /**
     * @param list<AbstractColumn> $columns the table's configured columns
     *
     * @throws BadRequestHttpException when the request carries criteria the provider cannot apply
     * @throws \LogicException         when the table declares summaries the provider cannot compute
     */
    public function assertRequestSupported(DataTableRequest $request, DataProviderInterface $provider, DataTable $table, array $columns): void
    {
        if ($provider instanceof DoctrineDataProvider && null !== $request->searchBuilder) {
            SearchBuilderFilter::assertSearchable($request->searchBuilder, array_values($columns));
        }

        foreach (self::QUERY_FEATURES as $feature) {
            if (DataSourceCapability::supports($provider, $feature)) {
                continue;
            }

            if (DataSourceFeature::Summaries === $feature) {
                if ([] !== ColumnResolver::filterSummarized($columns)) {
                    throw new \LogicException(\sprintf('Column summaries are not supported by %s. Remove summarize() or use a data provider that computes them.', self::providerName($provider)));
                }

                continue;
            }

            if ($this->requests($request, $feature, $table, $columns)) {
                throw self::unsupported($feature, $provider);
            }
        }
    }

    public static function unsupported(DataSourceFeature $feature, DataProviderInterface $provider): BadRequestHttpException
    {
        return new BadRequestHttpException(\sprintf('%s are not supported by %s. Use a DoctrineDataProvider or a custom DataProviderInterface for this table.', $feature->label(), self::providerName($provider)));
    }

    private function assertModeSatisfies(TableDefinition $definition): void
    {
        $mode = $definition->optIns->dataSourceMode;

        if (DataSourceMode::ClientHydrated === $mode || DataSourceMode::Inline === $mode) {
            $this->assertBrowserCanSummarize($definition->table);

            return;
        }

        $capability = $definition->optIns->knownCapability();

        if (null === $capability) {
            return;
        }

        foreach (self::QUERY_FEATURES as $feature) {
            if (!\in_array($feature, $capability, true) && $definition->requirements->has($feature)) {
                throw new \LogicException(\sprintf('%s are not supported on an API Platform collection, because it cannot apply them to every matching row. Remove them or read the table through Doctrine.', $feature->label()));
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

    /**
     * @param list<AbstractColumn> $columns
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

    private static function providerName(DataProviderInterface $provider): string
    {
        $class = $provider::class;
        $pos   = strrpos($class, '\\');

        return false === $pos ? $class : substr($class, $pos + 1);
    }
}
