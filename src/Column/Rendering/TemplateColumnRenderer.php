<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column\Rendering;

use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\TemplateAwareColumnInterface;
use Pentiminax\UX\DataTables\RowMapper\RowContext;
use Twig\Environment;

final class TemplateColumnRenderer
{
    /**
     * Twig keys reserved by the renderer.
     *
     * `row` is the object passed to mapRow(), `payload` the array it returned.
     *
     * `entity` is a deprecated alias of `row` for TemplateColumn templates only, and remains
     * reserved until it is removed. Detail rows and the edit modal expose their own `entity`,
     * which is neither an alias nor deprecated.
     */
    public const array RESERVED_CONTEXT_KEYS = ['entity', 'data', 'column', 'row', 'source', 'payload'];

    public function __construct(
        private readonly ?Environment $twig = null,
    ) {
    }

    /**
     * @param iterable<ColumnInterface> $columns
     */
    public function renderRow(array $row, mixed $mappedRow, iterable $columns): array
    {
        $renderedRow = $row;
        $payload     = $row;

        $source     = $mappedRow instanceof RowContext ? $mappedRow->source : $mappedRow;
        $contextRow = $mappedRow instanceof RowContext ? $mappedRow->item : $mappedRow;

        foreach ($columns as $column) {
            if (!$column instanceof TemplateAwareColumnInterface) {
                continue;
            }

            $rowKey = ColumnKeyResolver::rowKey($column);
            if (null === $rowKey) {
                continue;
            }

            $data = $this->resolveData(
                mappedRow: $contextRow,
                row: $payload,
                field: ColumnKeyResolver::readPath($column, $rowKey),
            );

            $context = [
                'row'     => $contextRow,
                'source'  => $source,
                'payload' => $payload,
                'data'    => $data,
                'column'  => $column->jsonSerialize(),
                'entity'  => $contextRow,
            ];

            foreach ($column->getTemplateParameters() as $key => $value) {
                if (!\in_array($key, self::RESERVED_CONTEXT_KEYS, true)) {
                    $context[$key] = $value;
                }
            }

            $renderedRow[$rowKey] = $this->renderTemplate($column->getTemplate(), $context);
        }

        return $renderedRow;
    }

    private function renderTemplate(string $template, array $context): string
    {
        if (null === $this->twig) {
            throw new \LogicException('Twig Environment is required to render TemplateColumn cells.');
        }

        return $this->twig->render($template, $context);
    }

    private function resolveData(mixed $mappedRow, array $row, string $field): mixed
    {
        $value = PropertyReader::readPath($row, $field);

        return $value ?? PropertyReader::readPath($mappedRow, $field);
    }
}
