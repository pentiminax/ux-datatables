<?php

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Util\PropertyReader;
use Twig\Environment;

final class TemplateColumnRenderer
{
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
        $contextRow  = $row;

        foreach ($columns as $column) {
            if (!$column instanceof TemplateColumn) {
                continue;
            }

            $field = $column->getField();
            $data  = $this->resolveData(mappedRow: $mappedRow, row: $contextRow, field: $field);

            $renderedRow[$field] = $this->renderTemplate($column->getTemplate(), [
                'entity' => $mappedRow,
                'data'   => $data,
                'column' => $column->jsonSerialize(),
                'row'    => $contextRow,
            ]);
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
