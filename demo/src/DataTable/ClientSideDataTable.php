<?php

declare(strict_types=1);

namespace App\DataTable;

use Pentiminax\UX\DataTables\Column\IconColumn;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Enum\Icon;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;

final class ClientSideDataTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield TextColumn::new('city', 'Warehouse');
        yield TextColumn::new('country', 'Country');
        yield NumberColumn::new('capacity', 'Capacity (m²)')->formatted();
        yield NumberColumn::new('staff', 'Staff');
        yield IconColumn::new('open', 'Open on Sundays')
            ->boolean()
            ->trueIcon(Icon::CircleCheck)
            ->falseIcon(Icon::Minus)
            ->trueColor('success')
            ->falseColor('secondary');
    }

    public function configureDataTable(DataTable $table): DataTable
    {
        return $table
            ->pageLength(5)
            ->lengthMenu([5, 10])
            ->data([
                ['city' => 'Lyon', 'country' => 'France', 'capacity' => 12500, 'staff' => 48, 'open' => true],
                ['city' => 'Rotterdam', 'country' => 'Netherlands', 'capacity' => 31000, 'staff' => 112, 'open' => true],
                ['city' => 'Leipzig', 'country' => 'Germany', 'capacity' => 18400, 'staff' => 63, 'open' => false],
                ['city' => 'Porto', 'country' => 'Portugal', 'capacity' => 7200, 'staff' => 21, 'open' => false],
                ['city' => 'Milan', 'country' => 'Italy', 'capacity' => 15800, 'staff' => 57, 'open' => true],
                ['city' => 'Montréal', 'country' => 'Canada', 'capacity' => 22300, 'staff' => 80, 'open' => false],
                ['city' => 'Osaka', 'country' => 'Japan', 'capacity' => 9600, 'staff' => 34, 'open' => true],
                ['city' => 'Austin', 'country' => 'United States', 'capacity' => 27750, 'staff' => 95, 'open' => true],
            ]);
    }
}
