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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

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
        private readonly ?RequestStack $requestStack = null,
    ) {
    }

    /**
     * The ids the table's permanent scope contains. A provider that cannot answer is refused,
     * unless the table declares unscopedMutations(), which keeps every id.
     *
     * @param list<int|string> $ids
     *
     * @return list<int|string>
     *
     * @throws MutationNotAllowedException when the scope cannot be checked
     */
    public function keepInScope(ResolvedDataTable $table, Request $request, string $field, array $ids): array
    {
        // Refused before the request is read, so a refusal never depends on the request.
        if (null === $this->scopeChecker($table)) {
            return $ids;
        }

        $call = $this->runtime->handle($table->table, $request);

        $checker = DataSourceCapability::rowScopeChecker($call->dataProvider()) ?? throw new MutationNotAllowedException();

        return $checker->filterIdentifiersInScope($call->request->withoutPagination(), $ids, $field);
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
     * @throws EntityNotFoundException when the row is out of scope or does not hold that related id
     */
    public function assertRelatedRowOwns(ResolvedDataTable $table, int|string $rowId, string $idPath, int|string $targetId): void
    {
        $this->assertInScope($table, $rowId);

        $row = $this->locator->locate($table->requireEntityClass(), $rowId)->entity;

        if ((string) $targetId !== RowIdNormalizer::normalize(PropertyReader::readPath($row, $idPath))) {
            throw new EntityNotFoundException();
        }
    }

    /**
     * @throws EntityNotFoundException     when the row is outside the table scope, whether or not it exists
     * @throws MutationNotAllowedException when the entity has no identifier field, there is no current request, or the scope cannot be checked
     */
    public function assertInScope(ResolvedDataTable $table, int|string $id): void
    {
        $entityClass = $table->requireEntityClass();
        $field       = $this->locator->manager($entityClass)->getClassMetadata($entityClass)->getIdentifier()[0] ?? null;

        if (null === $field) {
            throw new MutationNotAllowedException();
        }

        if ($table->definition->table->allowsUnscopedMutations()) {
            return;
        }

        $request = $this->requestStack?->getCurrentRequest() ?? throw new MutationNotAllowedException();

        $names = $table->definition->table->getForwardedQueryParameters();
        RequestQueryHydrator::hydrate($request, RequestQueryHydrator::forwardedFromBody($request, $names));

        if ([] === $this->keepInScope($table, $request, $field, [$id])) {
            throw new EntityNotFoundException();
        }
    }
}
