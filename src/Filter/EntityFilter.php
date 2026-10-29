<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Filter;

use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Query\NumericSearchTerm;
use Pentiminax\UX\DataTables\Query\RelationFieldResolver;
use Pentiminax\UX\DataTables\Query\UuidSearchTerm;

/**
 * Filters rows by a Doctrine association, offering a fixed list of the target entities.
 *
 * The options are loaded server-side when the table renders, through the same select control as
 * {@see ChoiceFilter}. The filter field names the association (e.g. "category"), and the submitted
 * identifiers are compared through bound parameters: `=` for one value, `IN` for several.
 *
 * Numeric and UUID/ULID identifiers are normalized and bound with their Doctrine type, and a
 * submitted value that does not fit the identifier type is ignored.
 *
 * The option list is a convenience, not authorization: any identifier can be submitted to the Ajax
 * route, so scope the rows themselves in the data provider or the table query.
 */
final class EntityFilter extends AbstractFilter
{
    use AppliesChoiceCondition;

    public const int DEFAULT_LIMIT = 200;

    /** @var class-string|null */
    private ?string $entityClass = null;

    /** @var string|(\Closure(object): string)|null */
    private string|\Closure|null $optionLabel = null;

    /** @var (\Closure(QueryBuilder): void)|null */
    private ?\Closure $optionsCustomizer = null;

    private int $limit = self::DEFAULT_LIMIT;

    private bool $multiple = false;

    /** @var array<string, string> identifier => label */
    private array $options = [];

    /**
     * @param class-string $class The target entity of the association
     */
    public function entity(string $class): static
    {
        $this->entityClass = $class;

        return $this;
    }

    /**
     * Property path of the option label, or a closure returning it. Without one, the first of the
     * display, name, label, and title properties is used, then __toString().
     *
     * @param string|(\Closure(object): string) $label
     */
    public function optionLabel(string|\Closure $label): static
    {
        $this->optionLabel = $label;

        return $this;
    }

    /**
     * Scope or order the listed entities. The closure receives a query builder selecting the entity
     * under the alias "e".
     *
     * @param \Closure(QueryBuilder): void $customizer
     */
    public function optionsQuery(\Closure $customizer): static
    {
        $this->optionsCustomizer = $customizer;

        return $this;
    }

    /**
     * Maximum number of options. Rendering fails above it instead of sending an unbounded list.
     */
    public function limit(int $limit): static
    {
        if ($limit < 1) {
            throw new \InvalidArgumentException('The option limit of an entity filter must be at least 1.');
        }

        $this->limit = $limit;

        return $this;
    }

    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;

        return $this;
    }

    /**
     * @return class-string|null
     */
    public function getEntityClass(): ?string
    {
        return $this->entityClass;
    }

    public function getOptionLabel(): string|\Closure|null
    {
        return $this->optionLabel;
    }

    public function getOptionsCustomizer(): ?\Closure
    {
        return $this->optionsCustomizer;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * @param array<string, string> $options
     *
     * @internal
     */
    public function setResolvedOptions(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function jsonSerialize(): array
    {
        return [
            ...parent::jsonSerialize(),
            'options'  => $this->options,
            'multiple' => $this->multiple,
        ];
    }

    protected function choiceBindingType(QueryBuilder $qb): ?string
    {
        $type = $this->identifierType($qb);

        if (null === $type || !(RelationFieldResolver::isNumericType($type) || RelationFieldResolver::isUuidType($type))) {
            return null;
        }

        return $type;
    }

    protected function normalizeChoiceValue(string $value, string $doctrineType): ?string
    {
        return RelationFieldResolver::isUuidType($doctrineType)
            ? UuidSearchTerm::normalize($value, $doctrineType)
            : NumericSearchTerm::normalize($value, $doctrineType);
    }

    protected function getType(): string
    {
        return 'select';
    }

    protected function doApply(QueryBuilder $qb, mixed $value, string $alias): void
    {
        $this->applyChoice($qb, $value, $alias, $this->multiple);
    }

    /**
     * Doctrine type of the identifier of the entity the filter field points at, null when the
     * field is not a plain association chain.
     */
    private function identifierType(QueryBuilder $qb): ?string
    {
        $roots = $qb->getRootEntities();
        if ([] === $roots) {
            return null;
        }

        try {
            $em       = $qb->getEntityManager();
            $metadata = $em->getClassMetadata($roots[0]);

            foreach (explode('.', $this->resolvedField()) as $segment) {
                if (!$metadata->hasAssociation($segment)) {
                    return null;
                }

                $metadata = $em->getClassMetadata($metadata->getAssociationTargetClass($segment));
            }

            return $metadata->getFieldMapping($metadata->getSingleIdentifierFieldName())->type;
        } catch (MappingException) {
            return null;
        }
    }
}
