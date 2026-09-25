<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Filter;

use Doctrine\ORM\QueryBuilder;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Choice filter matching an exact value (or one of several when multiple()).
 */
final class ChoiceFilter extends AbstractFilter
{
    /** @var array<string, string> value => label */
    private array $options = [];

    /** @var array<string, TranslatableInterface> value => translatable enum case */
    private array $translatableCases = [];

    private bool $multiple = false;

    /** @var class-string|null */
    private ?string $entityClass = null;

    /** @var string|(\Closure(object): string) */
    private string|\Closure $entityLabel = 'libelle';

    private string $entityValue = 'id';

    /** @var array<string, string> */
    private array $entityOrderBy = [];

    /** @var array<string, mixed> */
    private array $entityCriteria = [];

    /** @var (\Closure(object, QueryBuilder): void)|null */
    private ?\Closure $entityQueryBuilder = null;

    /**
     * Define the available options.
     *
     * Accepts an associative array using the `[label => value]` convention
     * (keys are human-readable labels, values are the stored values), or a list
     * of BackedEnum cases, or a BackedEnum class-string, or an Entity class-string.
     *
     * @param array<string|int, string|int>|list<\BackedEnum>|class-string<\BackedEnum>|class-string $options
     */
    public function options(array|string $options): self
    {
        $this->translatableCases = [];
        $this->entityClass       = null;

        if (\is_string($options)) {
            if (is_a($options, \BackedEnum::class, true)) {
                $this->options = $this->normalizeEnumOptions($options::cases());

                return $this;
            }

            if (class_exists($options)) {
                return $this->entity($options);
            }

            throw new \InvalidArgumentException(\sprintf('"%s" is neither a BackedEnum class nor a valid entity class.', $options));
        }

        if ($this->isBackedEnumList($options)) {
            $this->options = $this->normalizeEnumOptions($options);

            return $this;
        }

        $map = [];
        foreach ($options as $label => $value) {
            $map[(string) $value] = (string) $label;
        }
        $this->options = $map;

        return $this;
    }

    /**
     * Configure options to be loaded from a Doctrine entity.
     *
     * @param class-string $class The entity FQCN
     * @param string|(\Closure(object): string) $label Property path or closure returning the display label
     * @param string $value Property path for the option value (defaults to 'id')
     * @param array<string, string> $orderBy Sorting criteria (e.g. ['libelle' => 'ASC'])
     * @param array<string, mixed> $criteria Filtering criteria for findBy
     * @param (\Closure(object, QueryBuilder): void)|null $queryBuilder Custom query closure
     */
    public function entity(
        string $class,
        string|\Closure $label = 'libelle',
        string $value = 'id',
        array $orderBy = [],
        array $criteria = [],
        ?\Closure $queryBuilder = null,
    ): self {
        $this->translatableCases  = [];
        $this->options            = [];
        $this->entityClass        = $class;
        $this->entityLabel        = $label;
        $this->entityValue        = $value;
        $this->entityOrderBy      = $orderBy;
        $this->entityCriteria     = $criteria;
        $this->entityQueryBuilder = $queryBuilder;

        return $this;
    }

    public function hasEntityConfiguration(): bool
    {
        return null !== $this->entityClass;
    }

    public function getEntityClass(): ?string
    {
        return $this->entityClass;
    }

    public function getEntityLabel(): string|\Closure
    {
        return $this->entityLabel;
    }

    public function getEntityValue(): string
    {
        return $this->entityValue;
    }

    /**
     * @return array<string, string>
     */
    public function getEntityOrderBy(): array
    {
        return $this->entityOrderBy;
    }

    /**
     * @return array<string, mixed>
     */
    public function getEntityCriteria(): array
    {
        return $this->entityCriteria;
    }

    public function getEntityQueryBuilder(): ?\Closure
    {
        return $this->entityQueryBuilder;
    }

    /**
     * Set resolved choices [value => label].
     *
     * @param array<string, string> $options
     */
    public function setResolvedOptions(array $options): self
    {
        $this->translatableCases = [];
        $this->options           = $options;

        return $this;
    }

    public function multiple(bool $multiple = true): self
    {
        $this->multiple = $multiple;

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

    protected function getType(): string
    {
        return 'select';
    }

    protected function doApply(QueryBuilder $qb, mixed $value, string $alias): void
    {
        $expr = $this->resolveExpression($qb, $alias);
        if (null === $expr) {
            return;
        }

        if ($this->multiple) {
            $values = array_values(array_filter(
                \is_array($value) ? $value : [$value],
                static fn (mixed $item): bool => \is_scalar($item) && '' !== (string) $item,
            ));

            if ([] === $values) {
                return;
            }

            $param = $this->parameterName('in');
            $qb->andWhere(\sprintf('%s IN (:%s)', $expr, $param));
            $qb->setParameter($param, $values);

            return;
        }

        if (\is_array($value) || !\is_scalar($value) || '' === (string) $value) {
            return;
        }

        $param = $this->parameterName();
        $qb->andWhere(\sprintf('%s = :%s', $expr, $param));
        $qb->setParameter($param, $value);
    }

    /**
     * @param list<\BackedEnum> $cases
     *
     * @return array<string, string>
     */
    private function normalizeEnumOptions(array $cases): array
    {
        $map = [];
        foreach ($cases as $case) {
            $value       = (string) $case->value;
            $map[$value] = $this->resolveEnumLabel($case);

            if ($case instanceof TranslatableInterface) {
                $this->translatableCases[$value] = $case;
            }
        }

        return $map;
    }

    /**
     * Translate option labels backed by a TranslatableInterface enum case.
     *
     * Called by the RenderingPreparer when a translator is available, so the
     * translated label (and locale) is resolved at render time rather than at
     * configuration time.
     */
    public function translateLabels(TranslatorInterface $translator, ?string $locale = null): void
    {
        parent::translateLabels($translator, $locale);

        foreach ($this->translatableCases as $value => $case) {
            $this->options[$value] = $case->trans($translator, $locale);
        }
    }

    private function resolveEnumLabel(\BackedEnum $case): string
    {
        if (method_exists($case, 'getLabel')) {
            return (string) $case->getLabel();
        }

        if (method_exists($case, 'label')) {
            return (string) $case->label();
        }

        return $case->name;
    }

    /**
     * @param array<mixed> $options
     */
    private function isBackedEnumList(array $options): bool
    {
        if ([] === $options || !array_is_list($options)) {
            return false;
        }

        foreach ($options as $option) {
            if (!$option instanceof \BackedEnum) {
                return false;
            }
        }

        return true;
    }
}
