<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Column\AbstractColumn;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\DataTableHooksInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\Contracts\SearchPredicateBuilderInterface;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Query\DefaultSearchPredicateBuilder;
use Pentiminax\UX\DataTables\Query\Strategy\DefaultSearchStrategyRegistry;
use Pentiminax\UX\DataTables\Query\Strategy\SearchStrategyRegistry;
use Pentiminax\UX\DataTables\Runtime\TableContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Base class for every table the bundle renders: a class declaring its columns, actions, filters,
 * extensions, and options, from which the bundle runtime builds rendering payloads and Ajax responses.
 *
 * A table only declares. The bundle calls the configure*() methods once, keeps the result as an
 * immutable definition, and calls the hooks ({@see DataTableHooksInterface}) (mapRow(),
 * projectPage(), customizeQueryBuilder(), createDataProvider()) while it serves a request. The
 * instance is container-shared and holds no request, user, or runtime state of its own. The one
 * exception is rows() -- see its documentation.
 *
 * Treat the configure*() methods as pure functions of the class: they must not read the request,
 * the security token, the session, or the locale, and the definition they build is never rebuilt
 * for another request. See the purity contract in
 * docs/src/content/docs/reference/abstract-datatable.mdx ("Configuration Methods Must Be Pure").
 *
 * Request-dependent behavior belongs at the request-scoped boundaries instead: permission() on
 * columns and actions, customizeQueryBuilder() for the query (getCurrentRequest() in other hooks), and rows() --
 * the sanctioned way to inject rows a controller has already resolved -- for inline data, which
 * runs them through the same row-processing pipeline a provider would use.
 */
abstract class AbstractDataTable
{
    private ?TableContext $context = null;

    /** @var array<array-key, mixed>|null */
    private ?array $inlineRows = null;

    /**
     * The hooks of a copy of this table, bound to the context of one call the runtime makes.
     *
     * @internal
     */
    final public function bindContext(TableContext $context): BoundTableHooks
    {
        $bound          = clone $this;
        $bound->context = $context;

        return new BoundTableHooks(
            $bound,
            $bound->mapRow(...),
            $bound->projectPage(...),
            $bound->customizeQueryBuilder(...),
            $bound->createDataProvider(...),
            $bound->createSearchStrategyRegistry(...),
            $bound->createSearchPredicateBuilder(...),
        );
    }

    /**
     * Runs the bundle's configuration step on this instance, so state a configure method sets
     * reaches the hooks, and unbinds the context afterwards.
     *
     * @template T
     *
     * @param \Closure(self):T $configure
     *
     * @return T
     *
     * @internal
     */
    final public function configureWith(TableContext $context, \Closure $configure): mixed
    {
        $previous      = $this->context;
        $this->context = $context;

        try {
            return $configure($this);
        } finally {
            $this->context = $previous;
        }
    }

    /**
     * @internal
     */
    final public function resetDataTableState(): void
    {
        $this->inlineRows = null;
    }

    /**
     * The rows rows() received, not yet mapped.
     *
     * @return array<array-key, mixed>|null
     *
     * @internal
     */
    final public function getInlineRows(): ?array
    {
        return $this->inlineRows;
    }

    /**
     * The DataTableRequest being handled, for hooks such as mapRow() or projectPage().
     */
    final protected function getCurrentRequest(): ?DataTableRequest
    {
        return $this->context?->request;
    }

    protected function getHttpRequest(): ?Request
    {
        return $this->context?->httpRequest;
    }

    /**
     * @return iterable<AbstractColumn>
     */
    public function configureColumns(): iterable
    {
        $defaultColumns = $this->context?->defaultColumns;

        return null === $defaultColumns ? [] : $defaultColumns();
    }

    public function configureDataTable(DataTable $table): DataTable
    {
        return $table;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions;
    }

    /**
     * Declare the actions offered over the rows the user selected.
     *
     * Declaring one enables the Select extension with checkboxes and a multi-row style, and makes
     * every row carry a stable identifier the browser can key its selection on.
     */
    public function configureBulkActions(BulkActions $actions): BulkActions
    {
        return $actions;
    }

    /**
     * Declare user-facing filters rendered above the table.
     *
     * Override to add TextFilter, ChoiceFilter, TernaryFilter, DateRangeFilter
     * or a generic CheckboxFilter with a query() closure.
     */
    public function configureFilters(Filters $filters): Filters
    {
        return $filters;
    }

    protected function customizeQueryBuilder(QueryBuilder $qb, DataTableRequest $request): QueryBuilder
    {
        return $qb;
    }

    /**
     * Create and configure the search strategy registry.
     *
     * Override this method to register custom search strategies.
     */
    protected function createSearchStrategyRegistry(): SearchStrategyRegistry
    {
        return new DefaultSearchStrategyRegistry();
    }

    /**
     * Create the search predicate builder used by global search.
     *
     * Override this method to customize how global search builds a condition for a column,
     * e.g. to add type handling DefaultSearchPredicateBuilder does not cover.
     */
    protected function createSearchPredicateBuilder(): SearchPredicateBuilderInterface
    {
        return new DefaultSearchPredicateBuilder();
    }

    protected function mapRow(mixed $row): array
    {
        $mapper = $this->context?->defaultRowMapper
            ?? throw new \LogicException('mapRow() maps a row while the bundle runtime serves the table; it cannot run on a table used on its own.');

        return $mapper($row);
    }

    protected function createDataProvider(): ?DataProviderInterface
    {
        return null;
    }

    /**
     * Transform a complete, already-paginated page of source entities.
     *
     * Override to batch-enrich the page (load metrics, project to DTOs) without an N+1.
     * Return null (the default) to disable projection. When projecting, the returned list
     * must preserve the count and order of $items: columns and TemplateColumn Twig (`row`) then
     * read the projected item, while actions still receive the source entity.
     *
     * A server-side export has no page: it streams every filtered row, so the projector is called
     * once per batch of rows rather than once over the whole result set, and the batch size is not
     * the DataTables page length. Project each item from itself -- map it, or batch-load data keyed
     * by it. A projector whose output depends on which other items share the call (a rank, a
     * running total, a share of the batch's maximum) produces different values in an export than on
     * a page, and its values already shift with the page length on screen. Buffering every row to
     * project them together is what streaming exists to avoid; when a larger batch really is
     * needed, build the provider in {@see self::createDataProvider()} with a bigger
     * `exportChunkSize`.
     *
     * @param list<mixed> $items
     *
     * @return list<mixed>|null
     */
    protected function projectPage(array $items): ?array
    {
        return null;
    }

    /**
     * The configured columns after static permission filtering -- the exact list the Doctrine
     * path queries with.
     *
     * Pass it to an {@see \Pentiminax\UX\DataTables\DataProvider\ArrayDataProvider} built in
     * {@see self::createDataProvider()} so in-memory ordering and search honor the table's
     * column configuration, permissions included.
     *
     * @return list<AbstractColumn>
     */
    final protected function getResolvedColumns(): array
    {
        $columns = $this->context?->columns
            ?? throw new \LogicException('getResolvedColumns() is available while the bundle runtime calls a hook of the table.');

        return $columns();
    }

    final protected function createRowMapper(): RowMapperInterface
    {
        $rowMapper = $this->context?->rowMapper
            ?? throw new \LogicException('createRowMapper() is available while the bundle runtime calls a hook of the table.');

        return $rowMapper();
    }

    /**
     * Maps domain objects or arrays through the row pipeline at render time and embeds them as the
     * table's rows.
     *
     * This is the one piece of state a table keeps: the rows wait on the instance until the table
     * is rendered, and the `kernel.reset` tag clears them between requests under a worker runtime.
     *
     * @param array<array-key, mixed> $data
     */
    public function rows(array $data): void
    {
        $this->inlineRows = $data;
    }
}
