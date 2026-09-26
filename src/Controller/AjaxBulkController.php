<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Controller;

use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\Exception\MutationNotAllowedException;
use Pentiminax\UX\DataTables\Mutation\BulkActionRunner;
use Pentiminax\UX\DataTables\Mutation\BulkSelection;
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;
use Pentiminax\UX\DataTables\Security\MutationTokenValidator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;

/**
 * Runs one bulk action over the rows the browser reported as selected.
 *
 * The table is derived from its signed action token, never from a client-supplied class name, and
 * the static permission is checked before any entity is loaded. Every selected entity is
 * re-authorized individually inside {@see BulkActionRunner}.
 */
final class AjaxBulkController
{
    private readonly AuthorizationChecker $permissionChecker;

    public function __construct(
        private readonly BulkActionRunner $runner,
        private readonly MutationTokenValidator $tokenValidator,
        private readonly AjaxDataTableRegistry $registry,
        ?AuthorizationChecker $permissionChecker = null,
    ) {
        $this->permissionChecker = $permissionChecker ?? new AuthorizationChecker();
    }

    public function __invoke(Request $request, #[MapRequestPayload] AjaxBulkQueryDto $payload): Response
    {
        $this->tokenValidator->validate($request);

        $dataTable = $this->registry->resolveAction($payload->dataTable);
        $action    = $dataTable->findBulkAction($payload->action);

        if (null === $action) {
            throw new MutationNotAllowedException();
        }

        if (false === $this->permissionChecker->canExecuteAction($dataTable->dataTableClass, $action)) {
            throw new MutationNotAllowedException();
        }

        $selection = new BulkSelection(
            ids: $this->identifiers($payload->ids),
            allMatching: $payload->allMatching,
            deselectedIds: $this->identifiers($payload->deselectedIds),
            query: $payload->query,
        );

        $result = $this->runner->run(
            table: $dataTable,
            action: $action,
            selection: $selection,
            request: $request,
        );

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
