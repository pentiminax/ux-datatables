<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Filter;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\FilterInterface;
use Pentiminax\UX\DataTables\Query\RelationFieldResolver;

abstract class AbstractFilter implements FilterInterface
{
    protected ?string $label = null;

    protected ?string $field = null;

    protected ?string $placeholder = null;

    /**
     * Optional user-provided query closure overriding the default behaviour.
     *
     * @var (\Closure(QueryBuilder, mixed, string): void)|null
     */
    protected ?\Closure $queryCallback = null;

    final public function __construct(
        protected readonly string $name,
    ) {
    }

    public static function new(string $name): static
    {
        return new static($name);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Doctrine field path the filter targets. Defaults to the filter name.
     * Supports relation paths (e.g. "author.name").
     */
    public function field(string $field): static
    {
        $this->field = $field;

        return $this;
    }

    public function placeholder(string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    /**
     * Override the default condition with a custom closure.
     *
     * @param \Closure(QueryBuilder, mixed, string): void $callback
     */
    public function query(\Closure $callback): static
    {
        $this->queryCallback = $callback;

        return $this;
    }

    final public function apply(QueryBuilder $qb, mixed $value, string $alias): void
    {
        if (null !== $this->queryCallback) {
            ($this->queryCallback)($qb, $value, $alias);

            return;
        }

        $this->doApply($qb, $value, $alias);
    }

    public function jsonSerialize(): array
    {
        return array_filter([
            'name'        => $this->name,
            'type'        => $this->getType(),
            'label'       => $this->label ?? $this->humanizeName(),
            'placeholder' => $this->placeholder,
        ], static fn (mixed $value): bool => null !== $value);
    }

    /**
     * Frontend control type identifier (text, select, ternary, dateRange...).
     */
    abstract protected function getType(): string;

    /**
     * Default Doctrine condition, applied when no query() closure is set.
     */
    abstract protected function doApply(QueryBuilder $qb, mixed $value, string $alias): void;

    protected function resolvedField(): string
    {
        return $this->field ?? $this->name;
    }

    protected function resolveExpression(QueryBuilder $qb, string $alias): ?string
    {
        $field = $this->resolvedField();

        if (!RelationFieldResolver::supportsSearchFiltering($qb, $field)) {
            return null;
        }

        return RelationFieldResolver::resolve($qb, $alias, $field);
    }

    protected function parameterName(string $suffix = ''): string
    {
        $base = str_replace('.', '_', $this->resolvedField());

        return \sprintf('filter_%s%s', $base, '' === $suffix ? '' : '_'.$suffix);
    }

    private function humanizeName(): string
    {
        $words = preg_replace('/(?<!^)[A-Z]/', ' $0', $this->name) ?? $this->name;
        $words = str_replace(['_', '.'], ' ', $words);

        return ucfirst(strtolower(trim($words)));
    }
}
