<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

interface MercureHubUrlResolverInterface
{
    public function resolveHubUrl(): ?string;
}
