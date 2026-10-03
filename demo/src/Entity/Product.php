<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Category;
use App\Enum\ProductStatus;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class Product
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    public string $name = '';

    #[ORM\Column(length: 20, unique: true)]
    public string $sku = '';

    #[ORM\Column(enumType: Category::class)]
    public Category $category = Category::Home;

    #[ORM\Column(length: 120)]
    public string $image = '';

    /** Price in cents. */
    #[ORM\Column]
    #[Assert\PositiveOrZero]
    public int $price = 0;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    public int $stock = 0;

    #[ORM\Column(enumType: ProductStatus::class)]
    public ProductStatus $status = ProductStatus::Draft;

    #[ORM\Column]
    public bool $featured = false;

    #[ORM\Column(length: 180)]
    #[Assert\Email]
    public string $supplierEmail = '';

    #[ORM\Column(length: 255)]
    public string $url = '';

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }
}
