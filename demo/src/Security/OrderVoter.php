<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Order;
use App\Enum\OrderStatus;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Decides, row by row, which selected orders the user may cancel. Denied rows are skipped by the
 * bundle and reported as such. Business rules that are not about the user, like which orders can
 * ship, live in the bulk action handler instead.
 *
 * @extends Voter<string, Order>
 */
final class OrderVoter extends Voter
{
    public const string CANCEL = 'ORDER_CANCEL';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Order && self::CANCEL === $attribute;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return \in_array($subject->status, [OrderStatus::Pending, OrderStatus::Paid], true);
    }
}
