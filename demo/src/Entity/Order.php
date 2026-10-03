<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\OrderStatus;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'orders')]
#[ORM\Index(columns: ['placed_at'])]
#[ORM\Index(columns: ['status'])]
class Order
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 12, unique: true)]
    public string $reference = '';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    public Customer $customer;

    #[ORM\Column]
    public int $items = 1;

    /** Total in cents. */
    #[ORM\Column]
    public int $total = 0;

    #[ORM\Column(enumType: OrderStatus::class)]
    public OrderStatus $status = OrderStatus::Pending;

    #[ORM\Column]
    public bool $express = false;

    #[ORM\Column]
    public \DateTimeImmutable $placedAt;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $shippedAt = null;
}
