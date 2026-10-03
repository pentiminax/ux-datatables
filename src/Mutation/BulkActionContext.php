<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mutation;

use Doctrine\Persistence\ObjectManager;
use Pentiminax\UX\DataTables\Model\BulkAction;

/**
 * What a bulk action handler knows about the batch it is running.
 *
 * The counters are fed as chunks are consumed, so a handler reading them while it iterates sees
 * the batch's progress, not its final tally.
 */
final class BulkActionContext
{
    private int $processed = 0;

    private int $skipped = 0;

    /**
     * @param class-string $entityClass
     */
    public function __construct(
        public readonly string $entityClass,
        public readonly string $dataTableClass,
        public readonly BulkAction $action,
        public readonly ObjectManager $objectManager,
        public readonly int $selectedCount,
    ) {
    }

    /**
     * Entities handed to the handler so far.
     */
    public function processedCount(): int
    {
        return $this->processed;
    }

    /**
     * Selected entities excluded so far: gone, denied to the user, or declined with {@see skip()}.
     */
    public function skippedCount(): int
    {
        return $this->skipped;
    }

    /**
     * Report that the handler declined the entity it was just handed, for a business rule such as
     * "only paid orders can be shipped".
     *
     * The entity moves from the processed to the skipped tally, so the counts the user sees match
     * what the handler did. Call it once per entity, while iterating; it does not undo changes
     * already made to the entity.
     */
    public function skip(): void
    {
        if ($this->processed > 0) {
            --$this->processed;
        }

        ++$this->skipped;
    }

    /**
     * @internal
     */
    public function recordProcessed(): void
    {
        ++$this->processed;
    }

    /**
     * @internal
     */
    public function recordSkipped(int $count = 1): void
    {
        $this->skipped += $count;
    }
}
