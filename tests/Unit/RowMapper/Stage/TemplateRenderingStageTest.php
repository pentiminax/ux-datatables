<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\RowMapper\Stage;

use Pentiminax\UX\DataTables\Column\Rendering\TemplateColumnRenderer;
use Pentiminax\UX\DataTables\Column\TemplateColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\RowMapper\Stage\TemplateRenderingStage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[CoversClass(TemplateRenderingStage::class)]
final class TemplateRenderingStageTest extends TestCase
{
    #[Test]
    public function it_renders_template_column(): void
    {
        $stage = new TemplateRenderingStage(
            new TemplateColumnRenderer(
                new Environment(new ArrayLoader([
                    'badge.html.twig' => '<b>{{ data }}</b>',
                ]))
            )
        );

        $result = $stage->process(
            ['status' => 'active'],
            new \stdClass(),
            [TemplateColumn::new('status_display')->setField('status')->setTemplate('badge.html.twig')],
        );

        $this->assertSame('<b>active</b>', $result['status']);
    }

    #[Test]
    public function it_passes_through_non_template_columns(): void
    {
        $stage = new TemplateRenderingStage(
            new TemplateColumnRenderer(new Environment(new ArrayLoader([])))
        );

        $result = $stage->process(
            ['id' => 42],
            new \stdClass(),
            [TextColumn::new('id')],
        );

        $this->assertSame(['id' => 42], $result);
    }
}
