<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Controller;

use Pentiminax\UX\DataTables\Ajax\ResolvedDataTable;
use Pentiminax\UX\DataTables\Exception\EntityNotFoundException;
use Pentiminax\UX\DataTables\Mutation\BooleanMutationContext;
use Pentiminax\UX\DataTables\Mutation\BooleanMutationContextResolver;
use Pentiminax\UX\DataTables\Mutation\EntityMutator;
use Pentiminax\UX\DataTables\Mutation\RowScopeGuard;
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
        private readonly RowScopeGuard $scopeGuard,
    ) {
    }

    public function __invoke(Request $request, #[MapRequestPayload] AjaxEditRequestDto $payload): Response
    {
        $this->tokenValidator->validate($request);

        $context = $this->contextResolver->resolve($payload->dataTable, $payload->field);

        if (null !== $context->table) {
            $this->assertInScope($context->table, $context, $payload);
        }

        $this->mutator->setProperty(
            entityClass: $context->entityClass,
            id: $payload->id,
            field: $context->field,
            value: $payload->newValue,
            dataTableClass: $context->dataTableClass,
        );

        return new Response($payload->newValue ? '1' : '0');
    }

    private function assertInScope(ResolvedDataTable $table, BooleanMutationContext $context, AjaxEditRequestDto $payload): void
    {
        // A table without an entity class has no row scope to check against.
        if (null === $table->entityClass) {
            return;
        }

        if ($context->entityClass === $table->entityClass) {
            $this->scopeGuard->assertInScope($table, $payload->id);

            return;
        }

        if (null === $payload->rowId) {
            throw new EntityNotFoundException();
        }

        $this->scopeGuard->assertRelatedRowOwns($table, $payload->rowId, $context->toggleIdField, $payload->id);
    }
}
