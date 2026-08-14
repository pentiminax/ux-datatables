<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\FieldMapping;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Query builder mocks whose root entity metadata drives the field-type branches of the
 * search helpers.
 */
trait BuildsTypedFieldQueryBuilder
{
    /**
     * Root entity mapping a single scalar field to a given Doctrine type.
     *
     * A null $type leaves the root entity metadata unavailable, as it is for a query
     * builder whose root is not a mapped entity.
     */
    private function queryBuilderWithFieldType(string $field, ?string $type): MockObject&QueryBuilder
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->willReturn([]);

        if (null === $type) {
            return $qb;
        }

        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('hasAssociation')->with($field)->willReturn(false);
        $metadata->method('hasField')->with($field)->willReturn(true);
        $metadata->method('getFieldMapping')->with($field)->willReturn(new FieldMapping($type, $field, $field));

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getClassMetadata')->with('App\\Entity\\Product')->willReturn($metadata);

        $qb->method('getRootEntities')->willReturn(['App\\Entity\\Product']);
        $qb->method('getEntityManager')->willReturn($em);

        return $qb;
    }

    /**
     * Root entity mapping $field to an association, so the search helpers must refuse to
     * treat it as a scalar column until the configuration points at an explicit path.
     */
    private function queryBuilderWithAssociationField(string $field): MockObject&QueryBuilder
    {
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('hasAssociation')->with($field)->willReturn(true);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getClassMetadata')->willReturn($metadata);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->willReturn([]);
        $qb->method('getRootEntities')->willReturn(['App\\Entity\\Project']);
        $qb->method('getEntityManager')->willReturn($em);

        return $qb;
    }
}
