<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Runtime;

use Doctrine\Persistence\ManagerRegistry;
use Pentiminax\UX\DataTables\Column\Rendering\PropertyReader;
use Pentiminax\UX\DataTables\Filter\ChoiceFilter;
use Pentiminax\UX\DataTables\Model\DataTable;

final readonly class FilterEntityOptionsResolver
{
    public function __construct(
        private ?ManagerRegistry $doctrine = null,
    ) {
    }

    public function prepare(DataTable $table): void
    {
        $filters = $table->getFilters();
        if (null === $filters) {
            return;
        }

        foreach ($filters->getFilters() as $filter) {
            if (!$filter instanceof ChoiceFilter || !$filter->hasEntityConfiguration()) {
                continue;
            }

            $this->resolveFilter($filter);
        }
    }

    private function resolveFilter(ChoiceFilter $filter): void
    {
        $entityClass = $filter->getEntityClass();
        if (null === $entityClass) {
            return;
        }

        if (null === $this->doctrine) {
            throw new \LogicException(\sprintf('Cannot load choices for entity "%s": Doctrine ManagerRegistry is not available.', $entityClass));
        }

        $em = $this->doctrine->getManagerForClass($entityClass);
        if (null === $em) {
            throw new \InvalidArgumentException(\sprintf('No Doctrine entity manager found for class "%s".', $entityClass));
        }

        $queryBuilderClosure = $filter->getEntityQueryBuilder();
        if (null !== $queryBuilderClosure) {
            $repository = $em->getRepository($entityClass);
            $qb         = $repository->createQueryBuilder('e');
            $queryBuilderClosure($repository, $qb);
            $entities = $qb->getQuery()->getResult();
        } else {
            $orderBy = $filter->getEntityOrderBy();
            $entities = $em->getRepository($entityClass)->findBy(
                $filter->getEntityCriteria(),
                [] !== $orderBy ? $orderBy : null,
            );
        }

        $labelProperty = $filter->getEntityLabel();
        $valueProperty = $filter->getEntityValue();
        $options       = [];

        foreach ($entities as $entity) {
            $value = PropertyReader::readPath($entity, $valueProperty);
            if (null === $value) {
                continue;
            }

            if (\is_callable($labelProperty)) {
                $label = (string) $labelProperty($entity);
            } elseif (null !== $labelProperty && '' !== $labelProperty) {
                $label = PropertyReader::readPath($entity, $labelProperty);
                if (null === $label) {
                    $label = method_exists($entity, '__toString') ? (string) $entity : (string) $value;
                }
            } else {
                if (method_exists($entity, '__toString')) {
                    $label = (string) $entity;
                } else {
                    $label = PropertyReader::readPath($entity, 'libelle')
                        ?? PropertyReader::readPath($entity, 'name')
                        ?? PropertyReader::readPath($entity, 'label')
                        ?? PropertyReader::readPath($entity, 'title')
                        ?? PropertyReader::readPath($entity, 'display')
                        ?? (string) $value;
                }
            }

            $options[(string) $value] = (string) $label;
        }

        $filter->setResolvedOptions($options);
    }
}
