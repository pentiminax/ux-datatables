<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

interface ApiResourceCollectionUrlResolverInterface
{
    public function resolveCollectionUrl(string $entityClass): ?string;
}
