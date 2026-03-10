<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

use Pentiminax\UX\DataTables\Mercure\MercureConfig;

interface MercureConfigResolverInterface
{
    public function resolveMercureConfig(string $entityClass): ?MercureConfig;
}
