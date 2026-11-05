<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Controller;

use Pentiminax\UX\DataTables\Ajax\RowActionGate;
use Pentiminax\UX\DataTables\Ajax\RowActionRequest;
use Pentiminax\UX\DataTables\Mutation\EntityMutator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;

final class AjaxEditController
{
    public function __construct(
        private readonly EntityMutator $mutator,
        private readonly RowActionGate $gate,
    ) {
    }

    public function __invoke(Request $request, #[MapRequestPayload] AjaxEditRequestDto $payload): Response
    {
        $row = $this->gate->authorize($request, RowActionRequest::toggle($payload->dataTable, $payload->field, $payload->id, $payload->rowId));

        $this->mutator->toggleRow($row, $payload->newValue);

        return new Response($payload->newValue ? '1' : '0');
    }
}
