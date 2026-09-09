<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Controller;

use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\Enum\ActionType;
use Pentiminax\UX\DataTables\Exception\MutationNotAllowedException;
use Pentiminax\UX\DataTables\Mutation\EntityMutator;
use Pentiminax\UX\DataTables\Security\ActionPermissionContext;
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;
use Pentiminax\UX\DataTables\Security\MutationTokenValidator;
use Pentiminax\UX\DataTables\Security\Permission;
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
        private readonly ?AuthorizationChecker $permissionChecker = null,
    ) {
    }

    public function __invoke(Request $request, #[MapRequestPayload] AjaxEntityQueryDto $payload): Response
    {
        $this->tokenValidator->validate($request);

        $dataTable = $this->registry->resolveAction($payload->dataTable);
        $action    = $dataTable->findAction(ActionType::Delete);

        if (null === $action || false === $this->permissionChecker?->isGranted(Permission::DT_EXECUTE_ACTION, new ActionPermissionContext(
            $dataTable->dataTableClass,
            $action,
            null,
            false,
        ))) {
            throw new MutationNotAllowedException();
        }

        $this->mutator->delete($dataTable->requireEntityClass(), $payload->id, $dataTable->dataTableClass, $action);

        return new JsonResponse(['success' => true]);
    }
}
