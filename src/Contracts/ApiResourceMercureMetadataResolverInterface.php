<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

interface ApiResourceMercureMetadataResolverInterface
{
    /**
     * @return string[]
     */
    public function resolveTopics(string $entityClass): array;
}
