<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

interface EditModalTemplateResolverInterface
{
    public function resolveChromeTemplate(?string $dataTableClass): string;

    public function resolveBodyTemplate(): string;

    /**
     * @return ColumnInterface[]
     */
    public function resolveColumns(string $dataTableClass): array;
}
