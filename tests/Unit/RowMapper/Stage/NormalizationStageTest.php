<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\RowMapper\Stage;

use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\RowMapper\Stage\NormalizationStage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(NormalizationStage::class)]
final class NormalizationStageTest extends TestCase
{
    #[Test]
    public function it_leaves_scalar_values_unchanged(): void
    {
        $stage  = new NormalizationStage();
        $result = $stage->process(['title' => 'Hello'], 'original', [TextColumn::new('title')]);

        $this->assertSame(['title' => 'Hello'], $result);
    }

    #[Test]
    public function it_converts_stringable_to_string(): void
    {
        $stringable = new class implements \Stringable {
            public function __toString(): string
            {
                return 'Label';
            }
        };

        $stage  = new NormalizationStage();
        $result = $stage->process(['name' => $stringable], 'original', [TextColumn::new('name')]);

        $this->assertSame(['name' => 'Label'], $result);
    }

    #[Test]
    public function it_converts_non_stringable_object_to_null(): void
    {
        $stage  = new NormalizationStage();
        $result = $stage->process(['obj' => new \stdClass()], 'original', [TextColumn::new('obj')]);

        $this->assertNull($result['obj']);
    }

    #[Test]
    public function it_formats_datetime_with_date_column(): void
    {
        $date  = new \DateTimeImmutable('2024-06-01');
        $stage = new NormalizationStage();

        $result = $stage->process(
            ['date' => $date],
            'original',
            [DateColumn::new('date')->setFormat('d/m/Y')],
        );

        $this->assertSame(['date' => '01/06/2024'], $result);
    }

    #[Test]
    public function it_resolves_dotted_field_path(): void
    {
        $stage = new NormalizationStage();
        $obj   = new class {
            public string $name = 'Acme';
        };

        $result = $stage->process(
            ['company' => $obj],
            'original',
            [TextColumn::new('company')->setField('company.name')],
        );

        $this->assertSame(['company' => 'Acme'], $result);
    }

    #[Test]
    public function it_skips_columns_without_key(): void
    {
        $stage  = new NormalizationStage();
        $result = $stage->process(['title' => 'Hello'], 'original', [TextColumn::new('')]);

        $this->assertSame(['title' => 'Hello'], $result);
    }
}
