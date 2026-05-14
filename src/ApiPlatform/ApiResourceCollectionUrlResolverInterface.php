<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\ApiPlatform;

interface ApiResourceCollectionUrlResolverInterface
{
    public function resolveCollectionUrl(string $entityClass): ?string;
}
