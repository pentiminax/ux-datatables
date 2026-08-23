<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Support;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\FieldMapping;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\FilterInterface;

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

    /**
     * @param string|null $fieldType Doctrine type reported for every mapped field, or null to leave fields unmapped
     */
    private function createScalarFieldQueryBuilder(?string $fieldType = null): QueryBuilder
    {
        $metadata   = $this->createMock(ClassMetadata::class);
        $mappedType = $fieldType ?? 'string';
        $metadata->method('hasAssociation')->willReturn(false);
        $metadata->method('hasField')->willReturn(true);
        $metadata->method('getFieldMapping')->willReturnCallback(
            static fn (string $field): FieldMapping => new FieldMapping($mappedType, $field, $field)
        );

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

    /**
     * @param list<string>         $expectedWhere
     * @param array<string, mixed> $expectedParams
     * @param string|null          $fieldType      Doctrine type reported for every mapped field, or null to leave fields unmapped
     */
    private function assertFilterProduces(FilterInterface $filter, mixed $value, array $expectedWhere, array $expectedParams, ?string $fieldType = null): void
    {
        $filter->apply($this->createScalarFieldQueryBuilder($fieldType), $value, 'e');

        $this->assertSame($expectedWhere, $this->capturedWhere);
        $this->assertSame($expectedParams, $this->capturedParams);
    }
}
