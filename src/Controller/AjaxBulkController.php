<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Controller;

use Pentiminax\UX\DataTables\Ajax\BulkActionRequest;
use Pentiminax\UX\DataTables\Ajax\RowActionGate;
use Pentiminax\UX\DataTables\Mutation\BulkActionRunner;
use Pentiminax\UX\DataTables\Mutation\BulkSelection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;

/**
 * Runs one bulk action over the rows the browser reported as selected.
 *
 * {@see RowActionGate} derives the table from its signed action token, never from a client-supplied
 * class name, and checks the static permission before any entity is loaded.
 * {@see BulkActionRunner} keeps only the identifiers the table's scope contains and re-authorizes
 * each entity it loads.
 */
final class AjaxBulkController
{
    public function __construct(
        private readonly BulkActionRunner $runner,
        private readonly RowActionGate $gate,
    ) {
    }

    public function __invoke(Request $request, #[MapRequestPayload] AjaxBulkQueryDto $payload): Response
    {
        $bulk = $this->gate->authorizeBulk($request, new BulkActionRequest(
            dataTable: $payload->dataTable,
            action: $payload->action,
            query: $payload->query,
        ));

        $selection = new BulkSelection(
            ids: $this->identifiers($payload->ids),
            allMatching: $payload->allMatching,
            deselectedIds: $this->identifiers($payload->deselectedIds),
        );

        $result = $this->runner->run($bulk, $selection);

        return new JsonResponse([
            'success'   => true,
            'processed' => $result->processed,
            'skipped'   => $result->skipped,
        ]);
    }

    /**
     * The payload is unvalidated JSON, so keep only what can name a row.
     *
     * @param list<mixed> $values
     *
     * @return list<int|string>
     */
    private function identifiers(array $values): array
    {
        return array_values(array_filter(
            $values,
            static fn (mixed $value): bool => \is_int($value) || (\is_string($value) && '' !== $value),
        ));
    }
}
