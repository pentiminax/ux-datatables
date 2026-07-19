<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\RowMapper\Stage;

use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\RowMapper\RowContext;
use Pentiminax\UX\DataTables\RowMapper\Stage\BooleanSwitchMetadataStage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(BooleanSwitchMetadataStage::class)]
final class BooleanSwitchMetadataStageTest extends TestCase
{
    #[Test]
    public function it_adds_boolean_switch_metadata_from_default_id_field(): void
    {
        $stage = new BooleanSwitchMetadataStage();

        $result = $stage->process(
            ['active' => true],
            new BooleanSwitchMetadataFixture(42),
            [BooleanColumn::new('active')->renderAsSwitch()],
        );

        $this->assertSame([
            'active'                           => true,
            '__ux_datatables_boolean_switches' => ['active' => 42],
        ], $result);
    }

    #[Test]
    public function it_reads_the_id_from_row_context_source_before_projected_item(): void
    {
        $stage = new BooleanSwitchMetadataStage();

        $result = $stage->process(
            ['active' => true],
            new RowContext(
                source: new BooleanSwitchMetadataFixture('source-id'),
                item: new BooleanSwitchMetadataFixture('projected-id'),
            ),
            [BooleanColumn::new('active')->renderAsSwitch()],
        );

        $this->assertSame(['active' => 'source-id'], $result['__ux_datatables_boolean_switches']);
    }

    #[Test]
    public function it_supports_custom_toggle_id_and_effective_toggle_field(): void
    {
        $stage = new BooleanSwitchMetadataStage();

        $result = $stage->process(
            ['enabled' => true],
            ['uuid' => '018f2c3e-1234-7abc-9def-0123456789ab'],
            [
                BooleanColumn::new('enabled')
                    ->renderAsSwitch()
                    ->setToggleAjax(idField: 'uuid')
                    ->setCustomOption(BooleanColumn::OPTION_TOGGLE_FIELD, 'isEnabled'),
            ],
        );

        $this->assertSame([
            'isEnabled' => '018f2c3e-1234-7abc-9def-0123456789ab',
        ], $result['__ux_datatables_boolean_switches']);
    }

    #[Test]
    public function it_supports_multiple_switches(): void
    {
        $stage = new BooleanSwitchMetadataStage();

        $result = $stage->process(
            ['active' => true, 'verified' => false],
            new BooleanSwitchMetadataFixture(42),
            [
                BooleanColumn::new('active')->renderAsSwitch(),
                BooleanColumn::new('verified')->renderAsSwitch(),
            ],
        );

        $this->assertSame([
            'active'   => 42,
            'verified' => 42,
        ], $result['__ux_datatables_boolean_switches']);
    }

    #[Test]
    public function it_omits_metadata_when_the_id_is_missing_or_not_scalar_stringable(): void
    {
        $stage = new BooleanSwitchMetadataStage();

        $missing = $stage->process(
            ['active' => true],
            ['id' => null],
            [BooleanColumn::new('active')->renderAsSwitch()],
        );

        $invalid = $stage->process(
            ['active' => true],
            ['id' => new \stdClass()],
            [BooleanColumn::new('active')->renderAsSwitch()],
        );

        $this->assertArrayNotHasKey('__ux_datatables_boolean_switches', $missing);
        $this->assertArrayNotHasKey('__ux_datatables_boolean_switches', $invalid);
    }

    #[Test]
    public function it_uses_the_default_id_field_and_stringifies_stringable_ids(): void
    {
        $stage = new BooleanSwitchMetadataStage();
        $id    = new class implements \Stringable {
            public function __toString(): string
            {
                return 'stringable-id';
            }
        };

        $result = $stage->process(
            ['active' => true],
            ['id' => $id],
            [BooleanColumn::new('active')->renderAsSwitch()->setToggleAjax(idField: '')],
        );

        $this->assertSame(['active' => 'stringable-id'], $result['__ux_datatables_boolean_switches']);
    }

    #[Test]
    public function it_omits_metadata_for_a_switch_without_an_effective_field(): void
    {
        $stage = new BooleanSwitchMetadataStage();

        $result = $stage->process(
            mappedRow: ['value' => true],
            originalRow: ['id' => 42],
            columns: [BooleanColumn::new('')->renderAsSwitch()],
        );

        $this->assertArrayNotHasKey('__ux_datatables_boolean_switches', $result);
    }

    #[Test]
    public function it_ignores_non_switch_boolean_columns_and_other_columns(): void
    {
        $stage = new BooleanSwitchMetadataStage();

        $result = $stage->process(
            ['active' => true, 'name' => 'Ada'],
            new BooleanSwitchMetadataFixture(42),
            [
                BooleanColumn::new('active'),
                TextColumn::new('name'),
            ],
        );

        $this->assertArrayNotHasKey('__ux_datatables_boolean_switches', $result);
    }

    #[Test]
    public function it_discards_invalid_existing_metadata_keys(): void
    {
        $stage = new BooleanSwitchMetadataStage();

        $result = $stage->process(
            [
                'active'                           => true,
                '__ux_datatables_boolean_switches' => [
                    'verified' => 7,
                    ''         => 8,
                    0          => 9,
                ],
            ],
            new BooleanSwitchMetadataFixture(42),
            [BooleanColumn::new('active')->renderAsSwitch()],
        );

        $this->assertSame([
            'verified' => 7,
            'active'   => 42,
        ], $result['__ux_datatables_boolean_switches']);
    }

    #[Test]
    public function it_replaces_non_array_existing_metadata(): void
    {
        $stage = new BooleanSwitchMetadataStage();

        $result = $stage->process(
            [
                'active'                           => true,
                '__ux_datatables_boolean_switches' => 'invalid',
            ],
            new BooleanSwitchMetadataFixture(42),
            [BooleanColumn::new('active')->renderAsSwitch()],
        );

        $this->assertSame(['active' => 42], $result['__ux_datatables_boolean_switches']);
    }
}

final class BooleanSwitchMetadataFixture
{
    public function __construct(
        private readonly int|string $id,
    ) {
    }

    public function getId(): int|string
    {
        return $this->id;
    }
}
