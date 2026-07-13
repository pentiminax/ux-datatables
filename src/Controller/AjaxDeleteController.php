<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Controller;

use Pentiminax\UX\DataTables\Dto\AjaxDeleteRequestDto;
use Pentiminax\UX\DataTables\Mutation\EntityMutator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;

final class AjaxDeleteController
{
    public function __construct(
        private readonly EntityMutator $mutator,
    ) {
    }

    public function __invoke(#[MapRequestPayload] AjaxDeleteRequestDto $payload): Response
    {
        $this->mutator->delete($payload->entity, $payload->id);

        return new JsonResponse(['success' => true]);
    }
}
