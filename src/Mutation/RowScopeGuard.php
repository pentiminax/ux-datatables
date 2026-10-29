<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mutation;

use Pentiminax\UX\DataTables\Ajax\ResolvedDataTable;
use Pentiminax\UX\DataTables\Contracts\ScopedIdentifierProviderInterface;
use Pentiminax\UX\DataTables\Exception\EntityNotFoundException;
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
        private readonly ?RequestStack $requestStack = null,
    ) {
    }

    /**
     * The ids the table's permanent scope contains. A provider that cannot answer keeps them all.
     *
     * @param list<int|string> $ids
     *
     * @return list<int|string>
     */
    public static function keepInScope(ResolvedDataTable $table, Request $request, string $field, array $ids): array
    {
        $table->table->handleRequest($request);

        $dataTableRequest = $table->table->getRequest();
        $provider         = $table->table->getDataProvider();

        if (null === $dataTableRequest || !$provider instanceof ScopedIdentifierProviderInterface) {
            return $ids;
        }

        return $provider->filterIdentifiersInScope($dataTableRequest->withoutPagination(), $ids, $field);
    }

    /**
     * @throws EntityNotFoundException when the row is outside the table scope, whether or not it exists
     */
    public function assertInScope(ResolvedDataTable $table, int|string $id): void
    {
        $entityClass = $table->requireEntityClass();
        $field       = $this->locator->manager($entityClass)->getClassMetadata($entityClass)->getIdentifier()[0] ?? null;

        if (null === $field) {
            return;
        }

        $request = $this->requestStack?->getCurrentRequest() ?? new Request();

        if ([] === self::keepInScope($table, $request, $field, [$id])) {
            throw new EntityNotFoundException();
        }
    }
}
