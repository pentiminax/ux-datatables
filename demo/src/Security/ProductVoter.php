<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Product;
use App\Enum\ProductStatus;
use Pentiminax\UX\DataTables\Security\Permission;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Grants the row permissions the bundle asks for before editing, deleting, or expanding a product.
 *
 * The demo has no login, so every visitor may edit. A real application checks the user here.
 *
 * @extends Voter<string, Product>
 */
final class ProductVoter extends Voter
{
    private const array ATTRIBUTES = [
        Permission::DT_EDIT_ROW,
        Permission::DT_DELETE_ROW,
        Permission::DT_VIEW_ROW_DETAILS,
    ];

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Product && \in_array($attribute, self::ATTRIBUTES, true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return Permission::DT_DELETE_ROW !== $attribute || ProductStatus::Archived === $subject->status;
    }
}
