<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Controller;

use Pentiminax\UX\DataTables\Dto\AjaxEditRequestDto;
use Pentiminax\UX\DataTables\Mutation\EntityMutator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;

final class AjaxEditController
{
    public function __construct(
        private readonly EntityMutator $mutator,
    ) {
    }

    public function __invoke(#[MapRequestPayload] AjaxEditRequestDto $payload): Response
    {
        $this->mutator->setProperty(
            entityClass: $payload->entity,
            id: $payload->id,
            field: $payload->field,
            value: $payload->newValue,
        );

        return new Response($payload->newValue ? '1' : '0');
    }
}
