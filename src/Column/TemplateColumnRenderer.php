<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Util\PropertyReader;
use Twig\Environment;

final class TemplateColumnRenderer
{
    public const array RESERVED_CONTEXT_KEYS = ['entity', 'data', 'column', 'row'];

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

            $context = [
                'entity' => $mappedRow,
                'data'   => $data,
                'column' => $column->jsonSerialize(),
                'row'    => $contextRow,
            ];

            foreach ($column->getTemplateParameters() as $key => $value) {
                if (!\in_array($key, self::RESERVED_CONTEXT_KEYS, true)) {
                    $context[$key] = $value;
                }
            }

            $renderedRow[$field] = $this->renderTemplate($column->getTemplate(), $context);
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
