<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Runtime;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Pentiminax\UX\DataTables\Column\Rendering\PropertyReader;
use Pentiminax\UX\DataTables\Filter\ChoiceFilter;
use Pentiminax\UX\DataTables\Filter\EntityFilter;
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
            if ($filter instanceof EntityFilter) {
                $this->resolveEntityFilter($filter);

                continue;
            }

            if (!$filter instanceof ChoiceFilter || !$filter->hasEntityConfiguration()) {
                continue;
            }

            $this->resolveFilter($filter);
        }
    }

    private function resolveEntityFilter(EntityFilter $filter): void
    {
        $entityClass = $filter->getEntityClass();
        if (null === $entityClass) {
            throw new \LogicException(\sprintf('The entity filter "%s" has no entity class. Call entity() on it.', $filter->getName()));
        }

        $em = $this->entityManagerFor($entityClass);
        if (!$em instanceof EntityManagerInterface) {
            throw new \LogicException(\sprintf('The entity filter "%s" needs the Doctrine ORM, but "%s" is not managed by it.', $filter->getName(), $entityClass));
        }

        $metadata = $em->getClassMetadata($entityClass);
        $label    = $filter->getOptionLabel();

        $qb         = $em->createQueryBuilder()->select('e')->from($entityClass, 'e');
        $customizer = $filter->getOptionsCustomizer();
        if (null !== $customizer) {
            $customizer($qb);
        }

        if (\is_string($label) && $metadata->hasField($label) && [] === $qb->getDQLPart('orderBy')) {
            $qb->orderBy('e.'.$label, 'ASC');
        }

        $limit    = $filter->getLimit();
        $entities = $qb->setMaxResults($limit + 1)->getQuery()->getResult();

        if (\count($entities) > $limit) {
            throw new \LengthException(\sprintf('The entity filter "%s" lists more than %d "%s" options. Narrow the list with optionsQuery() or raise limit().', $filter->getName(), $limit, $entityClass));
        }

        $options = [];
        foreach ($entities as $entity) {
            $identifier = $metadata->getIdentifierValues($entity);
            if (1 !== \count($identifier)) {
                throw new \LogicException(\sprintf('The entity filter "%s" needs a single-column identifier on "%s".', $filter->getName(), $entityClass));
            }

            $value = reset($identifier);
            if (!\is_scalar($value) && !$value instanceof \Stringable) {
                throw new \LogicException(\sprintf('The entity filter "%s" needs an identifier on "%s" that converts to a string.', $filter->getName(), $entityClass));
            }

            $options[(string) $value] = $this->resolveLabel($entity, $label, $value);
        }

        $filter->setResolvedOptions($options);
    }

    private function entityManagerFor(string $entityClass): ObjectManager
    {
        if (null === $this->doctrine) {
            throw new \LogicException(\sprintf('Cannot load choices for entity "%s": Doctrine ManagerRegistry is not available.', $entityClass));
        }

        return $this->doctrine->getManagerForClass($entityClass)
            ?? throw new \InvalidArgumentException(\sprintf('No Doctrine entity manager found for class "%s".', $entityClass));
    }

    private function resolveFilter(ChoiceFilter $filter): void
    {
        $entityClass = $filter->getEntityClass();
        if (null === $entityClass) {
            return;
        }

        $em = $this->entityManagerFor($entityClass);

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
