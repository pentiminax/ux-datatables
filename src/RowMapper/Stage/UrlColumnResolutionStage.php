<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\RowMapper\Stage;

use Pentiminax\UX\DataTables\Column\Rendering\UrlColumnDataResolver;
use Pentiminax\UX\DataTables\Contracts\RowStageInterface;

final class UrlColumnResolutionStage implements RowStageInterface
{
    public function __construct(
        private readonly UrlColumnDataResolver $resolver,
    ) {
    }

    public function process(array $mappedRow, mixed $originalRow, array $columns): array
    {
        return $this->resolver->resolveRow($mappedRow, $originalRow, $columns);
    }
}
