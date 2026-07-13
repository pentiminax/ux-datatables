<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mercure;

interface MercureTopicResolverInterface
{
    /**
     * @return string[]
     */
    public function resolve(string $entityClass, ?string $dataTableClass = null): array;
}
