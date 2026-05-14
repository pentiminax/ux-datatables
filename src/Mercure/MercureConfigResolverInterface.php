<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mercure;

interface MercureConfigResolverInterface
{
    public function resolveMercureConfig(string $entityClass): ?MercureConfig;
}
