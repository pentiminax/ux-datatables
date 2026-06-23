<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model;

use Pentiminax\UX\DataTables\Contracts\FilterInterface;

/**
 * Ordered collection of user-facing filters declared via configureFilters().
 */
final class Filters implements \JsonSerializable
{
    /** @var array<string, FilterInterface> */
    private array $filters = [];

    public function add(FilterInterface $filter): self
    {
        $this->filters[$filter->getName()] = $filter;

        return $this;
    }

    public function remove(string $name): self
    {
        unset($this->filters[$name]);

        return $this;
    }

    public function get(string $name): ?FilterInterface
    {
        return $this->filters[$name] ?? null;
    }

    public function isEmpty(): bool
    {
        return [] === $this->filters;
    }

    public function count(): int
    {
        return \count($this->filters);
    }

    /**
     * @return list<FilterInterface>
     */
    public function getFilters(): array
    {
        return array_values($this->filters);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function jsonSerialize(): array
    {
        return array_values(array_map(
            static fn (FilterInterface $filter): array => $filter->jsonSerialize(),
            $this->filters,
        ));
    }
}
