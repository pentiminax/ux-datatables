<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Controller;

use Pentiminax\UX\DataTables\Dto\AjaxDeleteRequestDto;
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
    ) {
    }

    public function __invoke(Request $request, #[MapRequestPayload] AjaxDeleteRequestDto $payload): Response
    {
        $this->tokenValidator->validate($request);

        $this->mutator->delete($payload->entity, $payload->id, $payload->dataTableClass);

        return new JsonResponse(['success' => true]);
    }
}
