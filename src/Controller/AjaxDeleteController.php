<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Controller;

use Pentiminax\UX\DataTables\Ajax\RowActionGate;
use Pentiminax\UX\DataTables\Ajax\RowActionRequest;
use Pentiminax\UX\DataTables\Mutation\EntityMutator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;

final class AjaxDeleteController
{
    public function __construct(
        private readonly EntityMutator $mutator,
        private readonly RowActionGate $gate,
    ) {
    }

    public function __invoke(Request $request, #[MapRequestPayload] AjaxEntityQueryDto $payload): Response
    {
        $row = $this->gate->authorize($request, RowActionRequest::delete($payload->dataTable, $payload->id));

        $this->mutator->deleteRow($row);

        return new JsonResponse(['success' => true]);
    }
}
