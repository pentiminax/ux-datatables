<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Filter;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;

/**
 * Builds a QueryBuilder mock that captures andWhere/setParameter calls and
 * treats a given simple field as a scalar (non-association) column.
 */
trait BuildsFilterQueryBuilder
{
    /** @var list<string> */
    private array $capturedWhere = [];

    /** @var array<string, mixed> */
    private array $capturedParams = [];

    private function createScalarFieldQueryBuilder(): QueryBuilder
    {
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('hasAssociation')->willReturn(false);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getClassMetadata')->willReturn($metadata);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getRootEntities')->willReturn(['App\\Entity\\Project']);
        $qb->method('getEntityManager')->willReturn($em);
        $qb->method('getDQLPart')->willReturn([]);

        $qb->method('andWhere')->willReturnCallback(function (string $where) use ($qb): QueryBuilder {
            $this->capturedWhere[] = $where;

            return $qb;
        });

        $qb->method('setParameter')->willReturnCallback(function (string $name, mixed $value) use ($qb): QueryBuilder {
            $this->capturedParams[$name] = $value;

            return $qb;
        });

        return $qb;
    }
}
