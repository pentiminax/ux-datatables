<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\RowMapper\Stage;

use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\RowMapper\RowContext;
use Pentiminax\UX\DataTables\RowMapper\Stage\BooleanSwitchMetadataStage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(BooleanSwitchMetadataStage::class)]
final class BooleanSwitchMetadataStageTest extends TestCase
{
    /**
     * @param array<string, mixed>  $mappedRow
     * @param list<ColumnInterface> $columns
     * @param array<string, mixed>  $expected
     */
    #[Test]
    #[DataProvider('switchMetadataProvider')]
    public function it_exposes_switch_metadata_only_for_toggleable_columns_with_a_usable_id(
        array $mappedRow,
        mixed $originalRow,
        array $columns,
        array $expected,
    ): void {
        $result = (new BooleanSwitchMetadataStage())->process($mappedRow, $originalRow, $columns);

        $this->assertSame($expected, $result);
    }

    public static function switchMetadataProvider(): iterable
    {
        $stringableId = new class implements \Stringable {
            public function __toString(): string
            {
                return 'stringable-id';
            }
        };

        yield 'id read from the default id field' => [
            ['active' => true],
            new BooleanSwitchMetadataFixture(42),
            [BooleanColumn::new('active')->renderAsSwitch()],
            [
                'active'                                 => true,
                BooleanSwitchMetadataStage::METADATA_KEY => ['active' => 42],
            ],
        ];

        yield 'row context source wins over the projected item' => [
            ['active' => true],
            new RowContext(
                source: new BooleanSwitchMetadataFixture('source-id'),
                item: new BooleanSwitchMetadataFixture('projected-id'),
            ),
            [BooleanColumn::new('active')->renderAsSwitch()],
            [
                'active'                                 => true,
                BooleanSwitchMetadataStage::METADATA_KEY => ['active' => 'source-id'],
            ],
        ];

        yield 'custom toggle id field and effective toggle field' => [
            ['enabled' => true],
            ['uuid' => '018f2c3e-1234-7abc-9def-0123456789ab'],
            [
                BooleanColumn::new('enabled')
                    ->renderAsSwitch()
                    ->setToggleAjax(idField: 'uuid')
                    ->setCustomOption(BooleanColumn::OPTION_TOGGLE_FIELD, 'isEnabled'),
            ],
            [
                'enabled'                                => true,
                BooleanSwitchMetadataStage::METADATA_KEY => ['isEnabled' => '018f2c3e-1234-7abc-9def-0123456789ab'],
            ],
        ];

        yield 'multiple switches share the row id' => [
            ['active' => true, 'verified' => false],
            new BooleanSwitchMetadataFixture(42),
            [
                BooleanColumn::new('active')->renderAsSwitch(),
                BooleanColumn::new('verified')->renderAsSwitch(),
            ],
            [
                'active'                                 => true,
                'verified'                               => false,
                BooleanSwitchMetadataStage::METADATA_KEY => ['active' => 42, 'verified' => 42],
            ],
        ];

        yield 'empty id field falls back to the default one and stringifies the id' => [
            ['active' => true],
            ['id' => $stringableId],
            [BooleanColumn::new('active')->renderAsSwitch()->setToggleAjax(idField: '')],
            [
                'active'                                 => true,
                BooleanSwitchMetadataStage::METADATA_KEY => ['active' => 'stringable-id'],
            ],
        ];

        yield 'missing id exposes nothing' => [
            ['active' => true],
            ['id' => null],
            [BooleanColumn::new('active')->renderAsSwitch()],
            ['active' => true],
        ];

        yield 'non scalar and non stringable id exposes nothing' => [
            ['active' => true],
            ['id' => new \stdClass()],
            [BooleanColumn::new('active')->renderAsSwitch()],
            ['active' => true],
        ];

        yield 'empty id exposes nothing' => [
            ['active' => true],
            ['id' => ''],
            [BooleanColumn::new('active')->renderAsSwitch()],
            ['active' => true],
        ];

        yield 'switch without an effective field exposes nothing' => [
            ['value' => true],
            ['id' => 42],
            [BooleanColumn::new('')->renderAsSwitch()],
            ['value' => true],
        ];

        yield 'non switch and non boolean columns are ignored' => [
            ['active' => true, 'name' => 'Ada'],
            new BooleanSwitchMetadataFixture(42),
            [BooleanColumn::new('active'), TextColumn::new('name')],
            ['active' => true, 'name' => 'Ada'],
        ];

        yield 'invalid existing metadata entries are discarded' => [
            [
                'active'                                 => true,
                BooleanSwitchMetadataStage::METADATA_KEY => [
                    'verified' => 7,
                    'stale'    => '',
                    ''         => 8,
                    0          => 9,
                ],
            ],
            new BooleanSwitchMetadataFixture(42),
            [BooleanColumn::new('active')->renderAsSwitch()],
            [
                'active'                                 => true,
                BooleanSwitchMetadataStage::METADATA_KEY => ['verified' => 7, 'active' => 42],
            ],
        ];

        yield 'non array existing metadata is replaced' => [
            [
                'active'                                 => true,
                BooleanSwitchMetadataStage::METADATA_KEY => 'invalid',
            ],
            new BooleanSwitchMetadataFixture(42),
            [BooleanColumn::new('active')->renderAsSwitch()],
            [
                'active'                                 => true,
                BooleanSwitchMetadataStage::METADATA_KEY => ['active' => 42],
            ],
        ];
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
