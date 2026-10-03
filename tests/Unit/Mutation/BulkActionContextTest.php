<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Mutation;

use Doctrine\Persistence\ObjectManager;
use Pentiminax\UX\DataTables\Model\BulkAction;
use Pentiminax\UX\DataTables\Mutation\BulkActionContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(BulkActionContext::class)]
final class BulkActionContextTest extends TestCase
{
    #[Test]
    public function skip_moves_the_entity_just_handed_over_from_processed_to_skipped(): void
    {
        $context = $this->context();
        $context->recordProcessed();
        $context->recordProcessed();

        $context->skip();

        $this->assertSame(1, $context->processedCount());
        $this->assertSame(1, $context->skippedCount());
    }

    #[Test]
    public function skip_never_drives_the_processed_count_below_zero(): void
    {
        $context = $this->context();

        $context->skip();

        $this->assertSame(0, $context->processedCount());
        $this->assertSame(1, $context->skippedCount());
    }

    #[Test]
    public function the_runner_counters_add_up_with_skip(): void
    {
        $context = $this->context();
        $context->recordSkipped(2);
        $context->recordProcessed();
        $context->skip();

        $this->assertSame(0, $context->processedCount());
        $this->assertSame(3, $context->skippedCount());
    }

    private function context(): BulkActionContext
    {
        return new BulkActionContext(
            entityClass: \stdClass::class,
            dataTableClass: 'App\\DataTable\\Orders',
            action: BulkAction::new('touch'),
            objectManager: $this->createStub(ObjectManager::class),
            selectedCount: 3,
        );
    }
}
