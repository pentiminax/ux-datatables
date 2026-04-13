<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

/**
 * Marker interface for columns whose cell value is rendered by a Twig template.
 *
 * Implementations are picked up by the template renderer to render their cell
 * value at row-mapping time, with the row data and any extra parameters as context.
 */
interface TemplateAwareColumnInterface extends ColumnInterface
{
    /**
     * Returns the path to the Twig template used to render this column's cell.
     */
    public function getTemplate(): string;

    /**
     * Returns extra parameters merged into the Twig render context.
     *
     * @return array<string, mixed>
     */
    public function getTemplateParameters(): array;
}
