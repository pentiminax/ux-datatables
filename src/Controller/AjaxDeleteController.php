<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Controller;

use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\Dto\AjaxEntityQueryDto;
use Pentiminax\UX\DataTables\Mutation\EntityMutator;
use Pentiminax\UX\DataTables\Security\MutationTokenValidator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;

final class AjaxDeleteController
{
    public function __construct(
        private readonly EntityMutator $mutator,
        private readonly MutationTokenValidator $tokenValidator,
        private readonly AjaxDataTableRegistry $registry,
    ) {
    }

    public function __invoke(Request $request, #[MapRequestPayload] AjaxEntityQueryDto $payload): Response
    {
        $this->tokenValidator->validate($request);

        $dataTable = $this->registry->resolveAction($payload->dataTable);

        $this->mutator->delete($dataTable->requireEntityClass(), $payload->id, $dataTable->dataTableClass);

        return new JsonResponse(['success' => true]);
    }
}
