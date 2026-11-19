<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
final class ColumnTranslator
{
    /**
     * Translates the titles and the translatable enum choices of the given columns in place.
     *
     * @param iterable<AbstractColumn> $columns
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

    /** @internal */
    public static function applyTitle(AbstractColumn $column, string $title): void
    {
        $column->title($title);
    }
}
