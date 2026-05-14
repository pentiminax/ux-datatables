<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mercure;

interface MercureHubUrlResolverInterface
{
    public function resolveHubUrl(): ?string;
}
