<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Order;
use App\Enum\OrderStatus;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Decides, row by row, which selected orders a bulk action may touch. Denied rows are skipped by
 * the bundle and reported as such, so shipping a pending order never counts as shipped.
 *
 * @extends Voter<string, Order>
 */
final class OrderVoter extends Voter
{
    public const string SHIP   = 'ORDER_SHIP';
    public const string CANCEL = 'ORDER_CANCEL';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Order && \in_array($attribute, [self::SHIP, self::CANCEL], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return match ($attribute) {
            self::SHIP   => OrderStatus::Paid === $subject->status,
            self::CANCEL => \in_array($subject->status, [OrderStatus::Pending, OrderStatus::Paid], true),
            default      => false,
        };
    }
}
