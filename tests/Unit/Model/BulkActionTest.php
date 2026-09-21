<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Enum\Icon;
use Pentiminax\UX\DataTables\Model\BulkAction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(BulkAction::class)]
final class BulkActionTest extends TestCase
{
    #[Test]
    public function it_rejects_an_empty_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Bulk action name must not be empty.');

        BulkAction::new('   ');
    }

    #[Test]
    public function it_falls_back_to_the_name_when_no_label_is_given(): void
    {
        $this->assertSame('approve', BulkAction::new('approve')->jsonSerialize()['label']);
    }

    #[Test]
    public function it_rejects_a_chunk_size_below_one(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Bulk action chunk size must be at least 1, 0 given.');

        BulkAction::new('approve')->chunk(0);
    }

    #[Test]
    public function it_deselects_records_after_completion_by_default(): void
    {
        $this->assertTrue(BulkAction::new('approve')->shouldDeselectRecordsAfterCompletion());
        $this->assertFalse(
            BulkAction::new('approve')->deselectRecordsAfterCompletion(false)->shouldDeselectRecordsAfterCompletion()
        );
    }

    #[Test]
    public function it_keeps_a_lucide_icon_and_a_raw_icon_mutually_exclusive(): void
    {
        $action = BulkAction::new('approve')->icon(Icon::Check);
        $this->assertSame(Icon::Check->value, $action->jsonSerialize()['lucideIcon']);
        $this->assertArrayNotHasKey('icon', $action->jsonSerialize());

        $action->icon('bi bi-check');
        $this->assertSame('bi bi-check', $action->jsonSerialize()['icon']);
        $this->assertArrayNotHasKey('lucideIcon', $action->jsonSerialize());
    }

    #[Test]
    public function it_never_serializes_the_handler_the_permission_or_the_chunk_size(): void
    {
        $payload = BulkAction::new('approve', 'Approve')
            ->handler(static fn () => null)
            ->setPermission('ORDER_APPROVE')
            ->chunk(500)
            ->jsonSerialize();

        $this->assertSame(
            ['name', 'label', 'className', 'deselectAfterCompletion'],
            array_keys($payload),
        );
    }

    #[Test]
    public function it_separates_a_static_permission_from_a_per_row_one(): void
    {
        $static = BulkAction::new('approve')->setPermission('ORDER_APPROVE');
        $this->assertTrue($static->hasStaticPermission());
        $this->assertFalse($static->hasPerRowPermission());

        $perRow = BulkAction::new('approve')->setPermission('ORDER_APPROVE', static fn (object $o): object => $o);
        $this->assertFalse($perRow->hasStaticPermission());
        $this->assertTrue($perRow->hasPerRowPermission());
    }

    #[Test]
    public function a_denied_copy_carries_no_actionable_data(): void
    {
        $payload = BulkAction::new('approve', 'Approve')
            ->askConfirmation('Approve {count} orders?')
            ->successMessage('Done.')
            ->asDenied()
            ->jsonSerialize();

        $this->assertTrue($payload['denied']);
        $this->assertArrayNotHasKey('confirm', $payload);
        $this->assertArrayNotHasKey('successMessage', $payload);
        $this->assertArrayNotHasKey('deselectAfterCompletion', $payload);
    }

    #[Test]
    public function a_denied_copy_leaves_the_original_untouched(): void
    {
        $action = BulkAction::new('approve');
        $action->asDenied();

        $this->assertFalse($action->isDenied());
    }
}
