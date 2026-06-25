<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Filter;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\TranslatableFilterInterface;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Choice filter matching an exact value (or one of several when multiple()).
 */
final class ChoiceFilter extends AbstractFilter implements TranslatableFilterInterface
{
    /** @var array<string, string> value => label */
    private array $options = [];

    /** @var array<string, TranslatableInterface> value => translatable enum case */
    private array $translatableCases = [];

    private bool $multiple = false;

    /**
     * Define the available options.
     *
     * Accepts an associative array using the `[label => value]` convention
     * (keys are human-readable labels, values are the stored values), or a list
     * of BackedEnum cases, or a BackedEnum class-string.
     *
     * @param array<string|int, string|int>|list<\BackedEnum>|class-string<\BackedEnum> $options
     */
    public function options(array|string $options): self
    {
        $this->translatableCases = [];

        if (\is_string($options)) {
            if (!is_a($options, \BackedEnum::class, true)) {
                throw new \InvalidArgumentException(\sprintf('"%s" is not a BackedEnum class.', $options));
            }

            $this->options = $this->normalizeEnumOptions($options::cases());

            return $this;
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
