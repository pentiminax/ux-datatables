<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mutation;

use Doctrine\Persistence\ObjectManager;
use Pentiminax\UX\DataTables\Ajax\ResolvedDataTable;
use Pentiminax\UX\DataTables\Contracts\IdentifierCollectingDataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\MercurePublisherInterface;
use Pentiminax\UX\DataTables\Exception\InvalidBulkSelectionException;
use Pentiminax\UX\DataTables\Mercure\MercureTopicResolver;
use Pentiminax\UX\DataTables\Model\BulkAction;
use Pentiminax\UX\DataTables\Security\ActionPermissionContext;
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;
use Pentiminax\UX\DataTables\Security\Permission;
use Symfony\Component\HttpFoundation\Request;

/**
 * Runs a bulk action over the rows the browser reported as selected.
 *
 * A run is not a single transaction: each chunk is flushed on its own, so a chunk that throws
 * leaves the preceding ones committed. The client reloads on failure, so the table still shows
 * the partial result.
 */
final class BulkActionRunner
{
    public function __construct(
        private readonly EntityLocator $locator,
        private readonly AuthorizationChecker $permissionChecker,
        private readonly MutationFlusher $flusher,
        private readonly MercurePublisherInterface $publisher,
        private readonly MercureTopicResolver $topicResolver,
    ) {
    }

    /**
     * @throws InvalidBulkSelectionException when the selection cannot be resolved for this table
     */
    public function run(
        ResolvedDataTable $table,
        BulkAction $action,
        BulkSelection $selection,
        Request $request,
    ): BulkActionResult {
        $entityClass = $table->requireEntityClass();
        $ids         = $this->resolveIdentifiers($table, $selection, $request);

        if ([] === $ids) {
            throw InvalidBulkSelectionException::emptySelection();
        }

        $manager    = $this->locator->manager($entityClass);
        $identifier = $this->identifierField(
            $manager,
            $entityClass,
            $table->table->getConfiguredDataTable()->getBulkActions()?->getIdField(),
        );

        $context = new BulkActionContext(
            entityClass: $entityClass,
            dataTableClass: $table->dataTableClass,
            action: $action,
            objectManager: $manager,
            selectedCount: \count($ids),
        );

        $records = new BulkRecords(
            fn (): \Generator => $this->walk($manager, $entityClass, $identifier, $ids, $action, $context),
            \count($ids),
        );

        $handler = $action->getHandler()
            ?? throw new \LogicException(\sprintf('Bulk action "%s" must declare a handler.', $action->getName()));

        $handler($records, $context);

        // A handler may stop mid-chunk (BulkRecords::first(), an early break), leaving that
        // chunk's changes unflushed. Flushing once more persists them; it is a no-op otherwise.
        $this->flusher->flush($manager);

        $this->publish($entityClass, $table->dataTableClass, $action, $context->processedCount());

        return new BulkActionResult(
            processed: $context->processedCount(),
            skipped: $context->skippedCount(),
        );
    }

    /**
     * @param list<int|string> $ids
     *
     * @return \Generator<int, object>
     */
    private function walk(
        ObjectManager $manager,
        string $entityClass,
        string $identifier,
        array $ids,
        BulkAction $action,
        BulkActionContext $context,
    ): \Generator {
        $repository = $manager->getRepository($entityClass);

        foreach (array_chunk($ids, $action->getChunkSize()) as $chunk) {
            $entities = $repository->findBy([$identifier => $chunk]);

            $context->recordSkipped(\count($chunk) - \count($entities));

            foreach ($entities as $entity) {
                if (!$this->isGranted($action, $entity, $context->dataTableClass)) {
                    $context->recordSkipped();

                    continue;
                }

                $context->recordProcessed();

                yield $entity;
            }

            $this->flusher->flush($manager);
        }
    }

    private function isGranted(BulkAction $action, object $entity, string $dataTableClass): bool
    {
        return $this->permissionChecker->isGranted(Permission::DT_EXECUTE_ACTION, new ActionPermissionContext(
            $dataTableClass,
            $action,
            $entity,
            true,
        ));
    }

    /**
     * @return list<int|string>
     */
    private function resolveIdentifiers(ResolvedDataTable $table, BulkSelection $selection, Request $request): array
    {
        if (!$selection->allMatching) {
            return array_values(array_unique($selection->ids, \SORT_REGULAR));
        }

        if (true === $table->table->getConfiguredDataTable()->getBulkActions()?->isSelectCurrentPageOnly()) {
            throw InvalidBulkSelectionException::selectAllForbidden();
        }

        $this->hydrateRequest($request, $selection->query);
        $table->table->handleRequest($request);

        $dataTableRequest = $table->table->getRequest();
        $provider         = $table->table->getDataProvider();

        if (null === $dataTableRequest || !$provider instanceof IdentifierCollectingDataProviderInterface) {
            throw InvalidBulkSelectionException::selectAllUnsupported();
        }

        $ids        = $provider->collectIdentifiers($dataTableRequest->withoutPagination());
        $deselected = array_map($this->normalizeId(...), $selection->deselectedIds);

        return array_values(array_filter(
            $ids,
            fn (int|string $id): bool => !\in_array($this->normalizeId($id), $deselected, true),
        ));
    }

    /**
     * Put the DataTables parameters the browser captured back where the table reads them.
     *
     * The bulk endpoint receives JSON, so neither bag holds them. The request bag feeds
     * handleRequest(); the query bag feeds a customizeQueryBuilder() that scopes itself on
     * forwarded query parameters, which would otherwise read null and widen the batch to the
     * unscoped dataset.
     *
     * @param array<string, mixed> $query
     */
    private function hydrateRequest(Request $request, array $query): void
    {
        foreach ($query as $key => $value) {
            $name = (string) $key;

            if (!$request->request->has($name)) {
                $request->request->set($name, $value);
            }

            if (!$request->query->has($name)) {
                $request->query->set($name, $value);
            }
        }
    }

    private function normalizeId(int|string $id): string
    {
        return (string) $id;
    }

    private function identifierField(ObjectManager $manager, string $entityClass, ?string $configuredField): string
    {
        $identifiers = $manager->getClassMetadata($entityClass)->getIdentifier();

        if (null !== $configuredField && 'id' !== $configuredField) {
            return $configuredField;
        }

        return 1 === \count($identifiers) ? $identifiers[0] : ($configuredField ?? 'id');
    }

    private function publish(string $entityClass, string $dataTableClass, BulkAction $action, int $processed): void
    {
        if (0 === $processed) {
            return;
        }

        $this->publisher->publish($this->topicResolver->resolve($entityClass, $dataTableClass), [
            'type'      => 'bulk',
            'action'    => $action->getName(),
            'processed' => $processed,
        ]);
    }
}
