<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mutation;

use Pentiminax\UX\DataTables\Ajax\ResolvedDataTable;
use Pentiminax\UX\DataTables\Column\Rendering\PropertyReader;
use Pentiminax\UX\DataTables\Contracts\IdentifierDataProviderInterface;
use Pentiminax\UX\DataTables\Exception\EntityNotFoundException;
use Pentiminax\UX\DataTables\Exception\MutationNotAllowedException;
use Pentiminax\UX\DataTables\RowMapper\RowIdNormalizer;
use Pentiminax\UX\DataTables\Runtime\DataSourceCapability;
use Pentiminax\UX\DataTables\Runtime\DataTableCall;
use Pentiminax\UX\DataTables\Runtime\DataTableRuntime;

/**
 * Keeps single-row Ajax actions inside the permanent scope of their table, as bulk actions are.
 *
 * @internal
 */
final class RowScopeGuard
{
    public function __construct(
        private readonly EntityLocator $locator,
        private readonly DataTableRuntime $runtime,
    ) {
    }

    /**
     * The ids the table's permanent scope contains. The table keeps every id when it declares
     * unscopedMutations(); a provider that cannot answer is refused otherwise.
     *
     * @param \Closure():DataTableCall $call binds the table to the displayed request; run only when the scope is checked
     * @param list<int|string>         $ids
     *
     * @return list<int|string>
     *
     * @throws MutationNotAllowedException when the scope cannot be checked
     */
    public function keepInScope(ResolvedDataTable $table, \Closure $call, string $field, array $ids): array
    {
        if ($table->definition->table->allowsUnscopedMutations()) {
            return $ids;
        }

        return $this->filterInScope($call(), $field, $ids);
    }

    /**
     * The provider that checks the row scope, or null when the table opted out of the check. The
     * provider is resolved before any request is handled, so a refusal never depends on the request.
     *
     * @throws MutationNotAllowedException when the provider cannot check the row scope
     *
     * @internal
     */
    public function scopeChecker(ResolvedDataTable $table): ?IdentifierDataProviderInterface
    {
        return $this->checkerOf($table, $this->runtime->open($table->table));
    }

    /**
     * @param list<int|string> $ids
     *
     * @return list<int|string>
     */
    private function filterInScope(DataTableCall $call, string $field, array $ids): array
    {
        $checker = DataSourceCapability::rowScopeChecker($call->dataProvider()) ?? throw new MutationNotAllowedException();

        return $checker->filterIdentifiersInScope($call->request->withoutPagination(), $ids, $field);
    }

    private function checkerOf(ResolvedDataTable $table, DataTableCall $open): ?IdentifierDataProviderInterface
    {
        if ($table->definition->table->allowsUnscopedMutations()) {
            return null;
        }

        return DataSourceCapability::rowScopeChecker($open->dataProvider())
            ?? throw new MutationNotAllowedException();
    }

    /**
     * Ties a toggle on a related entity to the table row it was rendered in.
     *
     * @param \Closure():DataTableCall $call binds the table to the request; run only once the row scope can be checked
     *
     * @throws EntityNotFoundException when the row is out of scope or does not hold that related id
     */
    public function assertRelatedRowOwns(ResolvedDataTable $table, \Closure $call, int|string $rowId, string $idPath, int|string $targetId): void
    {
        $this->assertInScope($table, $call, $rowId);

        $row = $this->locator->locate($table->requireEntityClass(), $rowId)->entity;

        if ((string) $targetId !== RowIdNormalizer::normalize(PropertyReader::readPath($row, $idPath))) {
            throw new EntityNotFoundException();
        }
    }

    /**
     * @param \Closure():DataTableCall $call binds the table to the request; run only once the row scope can be checked
     *
     * @throws EntityNotFoundException     when the row is outside the table scope, whether or not it exists
     * @throws MutationNotAllowedException when the entity has no identifier field or the scope cannot be checked
     */
    public function assertInScope(ResolvedDataTable $table, \Closure $call, int|string $id): void
    {
        $entityClass = $table->requireEntityClass();
        $field       = $this->locator->manager($entityClass)->getClassMetadata($entityClass)->getIdentifier()[0] ?? null;

        if (null === $field) {
            throw new MutationNotAllowedException();
        }

        if ($table->definition->table->allowsUnscopedMutations()) {
            return;
        }

        $this->scopeChecker($table);

        if ([] === $this->filterInScope($call(), $field, [$id])) {
            throw new EntityNotFoundException();
        }
    }
}
