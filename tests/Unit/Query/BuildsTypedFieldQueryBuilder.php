<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\FieldMapping;
use Doctrine\ORM\QueryBuilder;

/**
 * Builds a QueryBuilder mock whose root entity maps a single scalar field to a
 * given Doctrine type, so field-type driven branches can be exercised.
 */
trait BuildsTypedFieldQueryBuilder
{
    private function queryBuilderWithFieldType(string $field, string $type): QueryBuilder
    {
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('hasAssociation')->with($field)->willReturn(false);
        $metadata->method('hasField')->with($field)->willReturn(true);
        $metadata->method('getFieldMapping')->with($field)->willReturn(new FieldMapping($type, $field, $field));

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getClassMetadata')->with('App\\Entity\\Product')->willReturn($metadata);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getRootEntities')->willReturn(['App\\Entity\\Product']);
        $qb->method('getEntityManager')->willReturn($em);

        return $qb;
    }
}
