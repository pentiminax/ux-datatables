<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;

/**
 * Whether a query multiplies its root rows through a to-many association. An untraceable join
 * counts as multiplying: a wrong page is worse than one extra query.
 */
final class CollectionJoinDetector
{
    public static function joinsCollection(QueryBuilder $qb, EntityManagerInterface $em, string $rootAlias, string $rootClass): bool
    {
        return self::walk($qb, $em, $rootAlias, $rootClass)[1];
    }

    /**
     * Metadata of the root and of every join alias that resolves to a mapped association.
     *
     * @return array<string, ClassMetadata<object>>
     */
    public static function aliasMetadata(QueryBuilder $qb, EntityManagerInterface $em, string $rootAlias, string $rootClass): array
    {
        return self::walk($qb, $em, $rootAlias, $rootClass)[0];
    }

    /**
     * @return array{array<string, ClassMetadata<object>>, bool}
     */
    private static function walk(QueryBuilder $qb, EntityManagerInterface $em, string $rootAlias, string $rootClass): array
    {
        $metadataByAlias = [$rootAlias => $em->getClassMetadata($rootClass)];
        $pending         = self::joins($qb);
        $multiplies      = false;

        while ([] !== $pending) {
            $resolved = false;

            foreach ($pending as $index => $join) {
                if (1 !== preg_match('/^([A-Za-z_]\w*)\.([A-Za-z_]\w*)$/', $join->getJoin(), $matches)) {
                    $multiplies = true;
                    unset($pending[$index]);

                    continue;
                }

                $parent = $metadataByAlias[$matches[1]] ?? null;
                if (null === $parent) {
                    continue;
                }

                $association = $matches[2];
                unset($pending[$index]);
                $resolved = true;

                if (!$parent->hasAssociation($association)) {
                    $multiplies = true;

                    continue;
                }

                $multiplies = $multiplies || $parent->isCollectionValuedAssociation($association);

                $alias = $join->getAlias();
                if (null !== $alias) {
                    $metadataByAlias[$alias] = $em->getClassMetadata($parent->getAssociationTargetClass($association));
                }
            }

            if (!$resolved) {
                return [$metadataByAlias, true];
            }
        }

        return [$metadataByAlias, $multiplies];
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
