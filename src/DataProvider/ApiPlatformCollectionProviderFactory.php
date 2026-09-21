<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\DataProvider;

use ApiPlatform\State\ProviderInterface;
use Pentiminax\UX\DataTables\ApiPlatform\ApiPlatformQueryParameterFactory;
use Pentiminax\UX\DataTables\ApiPlatform\ApiResourceCollectionUrlResolver;
use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\Query\Intent\DefaultDataTableQueryIntentFactory;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Builds an {@see ApiPlatformCollectionProvider} for one table. Registered only when API Platform
 * is installed, so AutoDataProviderFactory receives null otherwise.
 */
final class ApiPlatformCollectionProviderFactory
{
    public function __construct(
        private readonly ProviderInterface $stateProvider,
        private readonly ApiResourceCollectionUrlResolver $collectionResolver,
        private readonly ApiPlatformQueryParameterFactory $queryParameterFactory,
        private readonly DefaultDataTableQueryIntentFactory $intentFactory,
        private readonly ColumnResolver $columnResolver,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * Null when the entity exposes no collection operation, so a table that declares the
     * integration for an entity API Platform does not serve keeps the provider it had.
     *
     * @param list<ColumnInterface> $columns
     */
    public function create(
        string $entityClass,
        array $columns,
        RowMapperInterface $rowMapper,
        ?RowMapperInterface $exportRowMapper = null,
        ?string $dataTableClass = null,
    ): ?ApiPlatformCollectionProvider {
        if (null === $this->collectionResolver->resolveCollection($entityClass)) {
            return null;
        }

        return new ApiPlatformCollectionProvider(
            stateProvider: $this->stateProvider,
            collectionResolver: $this->collectionResolver,
            queryParameterFactory: $this->queryParameterFactory,
            intentFactory: $this->intentFactory,
            columnResolver: $this->columnResolver,
            requestStack: $this->requestStack,
            entityClass: $entityClass,
            columns: $columns,
            rowMapper: $rowMapper,
            exportRowMapper: $exportRowMapper,
            dataTableClass: $dataTableClass,
        );
    }
}
