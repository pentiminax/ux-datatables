<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Country;
use Doctrine\ORM\Mapping as ORM;
use Pentiminax\UX\DataTables\Attribute\DataTableColumn;
use Pentiminax\UX\DataTables\Attribute\DataTableFilter;
use Pentiminax\UX\DataTables\Column\ChoiceColumn;
use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\EmailColumn;
use Pentiminax\UX\DataTables\Column\IconColumn;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;

#[ORM\Entity]
class Customer
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    #[DataTableColumn(NumberColumn::class, ['title' => 'ID'])]
    public ?int $id = null;

    #[ORM\Column(length: 120)]
    #[DataTableColumn(TextColumn::class, ['title' => 'Name'])]
    #[DataTableFilter(options: ['label' => 'Name'])]
    public string $name = '';

    #[ORM\Column(length: 180)]
    #[DataTableColumn(EmailColumn::class, ['title' => 'Email'])]
    public string $email = '';

    #[ORM\Column(enumType: Country::class)]
    #[DataTableColumn(ChoiceColumn::class, ['title' => 'Country', 'choices' => Country::class])]
    #[DataTableFilter(options: ['label' => 'Country', 'multiple' => true])]
    public Country $country = Country::France;

    #[ORM\Column]
    #[DataTableColumn(IconColumn::class, [
        'title'      => 'VIP',
        'boolean'    => true,
        'trueIcon'   => 'crown',
        'falseIcon'  => 'minus',
        'trueColor'  => 'warning',
        'falseColor' => 'secondary',
    ])]
    #[DataTableFilter(options: ['label' => 'VIP'])]
    public bool $vip = false;

    #[ORM\Column]
    #[DataTableColumn(DateColumn::class, ['title' => 'Customer since', 'format' => 'Y-m-d'])]
    #[DataTableFilter(options: ['label' => 'Customer since'])]
    public \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
