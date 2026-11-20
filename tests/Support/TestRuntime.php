<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Support;

use Pentiminax\UX\DataTables\Runtime\DataTableRuntime;

final class TestRuntime
{
    use BuildsRuntime;

    public static function make(): DataTableRuntime
    {
        return self::buildRuntime();
    }
}
