<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query;

use Doctrine\ORM\QueryBuilder;

/**
 * doctrine/orm 3.7 deprecates string sort directions in favor of \SortDirection, while
 * 3.0-3.6 only accept strings even when PHP 8.6 or symfony/polyfill-php86 defines the enum.
 * Asking QueryBuilder::addOrderBy() which type it takes covers both.
 *
 * @internal
 */
final class DoctrineSortDirection
{
    public static function from(string $direction): string|\SortDirection
    {
        $orderType = (string) (new \ReflectionParameter([QueryBuilder::class, 'addOrderBy'], 1))->getType();
        if (!str_contains($orderType, 'SortDirection')) {
            return $direction;
        }

        return 'desc' === strtolower($direction) ? \SortDirection::Descending : \SortDirection::Ascending;
    }
}
