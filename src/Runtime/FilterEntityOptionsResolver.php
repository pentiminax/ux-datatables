<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Runtime;

use Doctrine\Persistence\ManagerRegistry;
use Pentiminax\UX\DataTables\Column\Rendering\PropertyReader;
use Pentiminax\UX\DataTables\Filter\ChoiceFilter;
use Pentiminax\UX\DataTables\Model\DataTable;

final readonly class FilterEntityOptionsResolver
{
    private const array DEFAULT_LABEL_PATHS = ['display', 'name', 'label', 'title'];

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
            $orderBy  = $filter->getEntityOrderBy();
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

            $options[(string) $value] = $this->resolveLabel($entity, $labelProperty, $value);
        }

        $filter->setResolvedOptions($options);
    }

    private function resolveLabel(object $entity, string|\Closure|null $labelProperty, mixed $value): string
    {
        if ($labelProperty instanceof \Closure) {
            return (string) $labelProperty($entity);
        }

        $labelPaths = null !== $labelProperty && '' !== $labelProperty
            ? [$labelProperty]
            : self::DEFAULT_LABEL_PATHS;

        foreach ($labelPaths as $labelPath) {
            $label = PropertyReader::readPath($entity, $labelPath);
            if (null !== $label) {
                return (string) $label;
            }
        }

        return $entity instanceof \Stringable ? (string) $entity : (string) $value;
    }
}
