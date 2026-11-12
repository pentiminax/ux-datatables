<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
final class ColumnTranslator
{
    /**
     * Translates the titles and the translatable enum choices of the given columns in place.
     *
     * @param iterable<ColumnInterface> $columns
     */
    public static function translate(iterable $columns, TranslatorInterface $translator): void
    {
        foreach ($columns as $column) {
            $title = $column->getTitle();
            if (null !== $title) {
                self::applyTitle($column, $translator->trans($title));
            }

            if ($column instanceof ChoiceColumn) {
                $column->translateLabels($translator);
            }
        }
    }

    private static function applyTitle(ColumnInterface $column, string $title): void
    {
        if ($column instanceof AbstractColumn) {
            $column->title($title);

            return;
        }

        if (method_exists($column, 'setTitle')) {
            $column->setTitle($title);
        }
    }
}
