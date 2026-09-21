<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Security;

use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class SecurityVoter extends Voter
{
    public function __construct(
        private readonly AccessDecisionManagerInterface $decisionManager,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return match ($attribute) {
            Permission::DT_ACCESS_TABLE   => $subject instanceof AbstractDataTable,
            Permission::DT_EXECUTE_ACTION => $subject instanceof ActionPermissionContext,
            default                       => false,
        };
    }

    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        TokenInterface $token,
        ?Vote $vote = null,
    ): bool {
        return match ($attribute) {
            Permission::DT_ACCESS_TABLE   => $this->voteOnTable($subject, $token),
            Permission::DT_EXECUTE_ACTION => $this->voteOnAction($subject, $token),
            default                       => false,
        };
    }

    private function voteOnTable(AbstractDataTable $dataTable, TokenInterface $token): bool
    {
        $permission = $dataTable->getConfiguredDataTable()->getPermission();

        if (null === $permission) {
            return true;
        }

        return $this->decisionManager->decide($token, [$permission], $dataTable);
    }

    private function voteOnAction(ActionPermissionContext $context, TokenInterface $token): bool
    {
        $permission = $context->action->getPermission();

        if (null === $permission) {
            return true;
        }

        $resolver = $context->action->getPermissionSubjectResolver();
        if (null === $resolver) {
            return $this->decisionManager->decide($token, [$permission]);
        }

        if (!$context->hasRowContext) {
            return true;
        }

        return $this->decisionManager->decide($token, [$permission], $resolver($context->currentSource));
    }
}
