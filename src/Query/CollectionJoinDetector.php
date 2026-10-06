<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;

/**
 * Tells whether a query multiplies its root rows through a collection-valued association.
 *
 * LIMIT/OFFSET count SQL rows, so a query joining a to-many association paginates joined rows
 * rather than root entities. Joins are followed through their aliases (`e` -> `tags` ->
 * `tags_group`), and any join that cannot be traced back to a mapped association -- an entity
 * class joined with WITH, an alias the query never declared -- is treated as multiplying: a
 * wrongly paginated page is worse than one extra query.
 */
final class CollectionJoinDetector
{
    public static function joinsCollection(QueryBuilder $qb, EntityManagerInterface $em, string $rootAlias, string $rootClass): bool
    {
        $metadataByAlias = [$rootAlias => $em->getClassMetadata($rootClass)];
        $pending         = self::joins($qb);

        while ([] !== $pending) {
            $resolved = false;

            foreach ($pending as $index => $join) {
                if (1 !== preg_match('/^([A-Za-z_]\w*)\.([A-Za-z_]\w*)$/', $join->getJoin(), $matches)) {
                    return true;
                }

                $parent = $metadataByAlias[$matches[1]] ?? null;
                if (null === $parent) {
                    continue;
                }

                $association = $matches[2];
                if (!$parent->hasAssociation($association) || $parent->isCollectionValuedAssociation($association)) {
                    return true;
                }

                $alias = $join->getAlias();
                if (null !== $alias) {
                    $metadataByAlias[$alias] = $em->getClassMetadata($parent->getAssociationTargetClass($association));
                }

                unset($pending[$index]);
                $resolved = true;
            }

            if (!$resolved) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, Join>
     */
    private static function joins(QueryBuilder $qb): array
    {
        $joins = [];

        foreach ($qb->getDQLPart('join') as $rootJoins) {
            foreach ($rootJoins as $join) {
                $joins[] = $join;
            }
        }

        return $joins;
    }
}
