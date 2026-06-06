<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column\Rendering;

use Pentiminax\UX\DataTables\Column\UrlColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\RowMapper\RowContext;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class UrlColumnDataResolver
{
    public const string ROW_URLS_KEY = '__ux_datatables_urls';

    public function __construct(
        private readonly ?UrlGeneratorInterface $urlGenerator = null,
    ) {
    }

    /**
     * @param iterable<ColumnInterface> $columns
     */
    public function resolveRow(array $row, mixed $sourceRow, iterable $columns): array
    {
        if ($sourceRow instanceof RowContext) {
            $sourceRow = $sourceRow->source;
        }

        $urls = [];

        foreach ($columns as $column) {
            if (!$column instanceof UrlColumn || !$column->hasUrlResolver()) {
                continue;
            }

            $key = $column->getData() ?? $column->getName();
            if ('' === $key) {
                continue;
            }

            $url = $column->resolveUrl($sourceRow, $this->urlGenerator);
            if (null === $url) {
                continue;
            }

            $urls[$key] = $url;
        }

        if ([] === $urls) {
            return $row;
        }

        $existingUrls = $row[self::ROW_URLS_KEY] ?? [];
        if (!\is_array($existingUrls)) {
            $existingUrls = [];
        }

        $row[self::ROW_URLS_KEY] = [...$existingUrls, ...$urls];

        return $row;
    }
}
