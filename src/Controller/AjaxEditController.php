<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Controller;

use Pentiminax\UX\DataTables\Dto\AjaxEditRequestDto;
use Pentiminax\UX\DataTables\Mutation\BooleanMutationContextResolver;
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
        private readonly BooleanMutationContextResolver $contextResolver,
    ) {
    }

    public function __invoke(Request $request, #[MapRequestPayload] AjaxEditRequestDto $payload): Response
    {
        $this->tokenValidator->validate($request);

        $context = $this->contextResolver->resolve($payload->dataTable, $payload->field);

        $this->mutator->setProperty(
            entityClass: $context->entityClass,
            id: $payload->id,
            field: $context->field,
            value: $payload->newValue,
            dataTableClass: $context->dataTableClass,
        );

        return new Response($payload->newValue ? '1' : '0');
    }
}
