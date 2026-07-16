<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Controller;

use Pentiminax\UX\DataTables\Dto\AjaxEditRequestDto;
use Pentiminax\UX\DataTables\Mutation\EntityMutator;
use Pentiminax\UX\DataTables\Security\MutationTokenValidator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;

final class AjaxEditController
{
    public function __construct(
        private readonly EntityMutator $mutator,
        private readonly MutationTokenValidator $tokenValidator,
    ) {
    }

    public function __invoke(Request $request, #[MapRequestPayload] AjaxEditRequestDto $payload): Response
    {
        $this->tokenValidator->validate($request);

        $this->mutator->setProperty(
            entityClass: $payload->entity,
            id: $payload->id,
            field: $payload->field,
            value: $payload->newValue,
            dataTableClass: $payload->dataTableClass,
        );

        return new Response($payload->newValue ? '1' : '0');
    }
}
