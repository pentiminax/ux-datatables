<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures;

use Pentiminax\UX\DataTables\Contracts\DataTableDefaultsConfiguratorInterface;
use Pentiminax\UX\DataTables\Model\DataTable;

final class StateSaveDefaultsConfigurator implements DataTableDefaultsConfiguratorInterface
{
    public function configure(DataTable $table): void
    {
        $table->stateSave(true);
    }
}
