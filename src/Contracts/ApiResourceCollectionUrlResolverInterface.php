<?php

namespace Pentiminax\UX\DataTables\Contracts;

interface ApiResourceCollectionUrlResolverInterface
{
    public function resolveCollectionUrl(string $entityClass): ?string;
}
