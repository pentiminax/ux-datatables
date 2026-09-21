<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mutation;

/**
 * The entities a bulk action handler operates on.
 *
 * Iteration is lazy and single-pass: the runner loads one chunk, authorizes each entity, and
 * yields the authorized ones, so a handler that iterates once never holds more than one chunk
 * plus whatever it keeps itself. A second iteration throws, because the first one consumed the
 * underlying generator.
 *
 * {@see self::count()} reports the number of *selected* records, not the authorized ones:
 * authorization needs the entity, so an authorized count cannot exist before iteration. Read
 * {@see BulkActionContext::processedCount()} afterwards for what the batch actually touched.
 */
final class BulkRecords implements \IteratorAggregate, \Countable
{
    private bool $consumed = false;

    /**
     * @param \Closure():\Generator<int, object> $entities
     */
    public function __construct(
        private readonly \Closure $entities,
        private readonly int $selectedCount,
    ) {
    }

    /**
     * @return \Traversable<int, object>
     */
    public function getIterator(): \Traversable
    {
        if ($this->consumed) {
            throw new \LogicException('BulkRecords can only be iterated once.');
        }

        $this->consumed = true;

        yield from ($this->entities)();
    }

    /**
     * Number of selected records, before per-row authorization.
     */
    public function count(): int
    {
        return $this->selectedCount;
    }

    public function isEmpty(): bool
    {
        return 0 === $this->selectedCount;
    }

    /**
     * @template T
     *
     * @param callable(object):T $callback
     *
     * @return list<T>
     */
    public function map(callable $callback): array
    {
        $results = [];

        foreach ($this as $entity) {
            $results[] = $callback($entity);
        }

        return $results;
    }

    /**
     * @param callable(object):bool $callback
     *
     * @return list<object>
     */
    public function filter(callable $callback): array
    {
        $results = [];

        foreach ($this as $entity) {
            if ($callback($entity)) {
                $results[] = $entity;
            }
        }

        return $results;
    }

    public function first(): ?object
    {
        foreach ($this as $entity) {
            return $entity;
        }

        return null;
    }

    /**
     * Load every authorized entity at once.
     *
     * Defeats the chunking this class exists for: on a select-all over a large filtered set, the
     * whole batch ends up in memory.
     *
     * @return list<object>
     */
    public function toArray(): array
    {
        return iterator_to_array($this, false);
    }
}
