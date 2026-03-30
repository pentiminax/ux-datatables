<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\RowMapper;

use Pentiminax\UX\DataTables\Column\ActionColumn;
use Pentiminax\UX\DataTables\Column\Rendering\ActionRowDataResolver;
use Pentiminax\UX\DataTables\Column\Rendering\TemplateColumnRenderer;
use Pentiminax\UX\DataTables\Column\TemplateColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\RowMapper\RowProcessingPipeline;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[CoversClass(RowProcessingPipeline::class)]
final class RowProcessingPipelineTest extends TestCase
{
    #[Test]
    public function it_applies_the_base_mapper_when_no_optional_stage_is_configured(): void
    {
        $pipeline = new RowProcessingPipeline(
            baseMapper: static fn (array $row): array => ['id' => $row['id']],
            columns: [TextColumn::new('id')],
        );

        $mappedRow = $pipeline->map(['id' => 9, 'title' => 'Alien']);

        $this->assertSame(['id' => 9], $mappedRow);
    }

    #[Test]
    public function it_renders_template_columns_after_the_base_mapper(): void
    {
        $pipeline = new RowProcessingPipeline(
            baseMapper: static fn (TemplateRow $row): array => ['id' => $row->id],
            columns: [
                TextColumn::new('id'),
                TemplateColumn::new('status_display')
                    ->setField('status')
                    ->setTemplate('datatable/columns/status_badge.html.twig'),
            ],
            templateColumnRenderer: new TemplateColumnRenderer(
                new Environment(new ArrayLoader([
                    'datatable/columns/status_badge.html.twig' => '<span>{{ row.id }}-{{ data }}</span>',
                ]))
            ),
        );

        $mappedRow = $pipeline->map(new TemplateRow(id: 5, status: 'active'));

        $this->assertSame([
            'id'     => 5,
            'status' => '<span>5-active</span>',
        ], $mappedRow);
    }

    #[Test]
    public function it_resolves_action_urls_after_mapping(): void
    {
        $actions = (new Actions())
            ->add(Action::detail()->linkToUrl(static fn (array $row): string => '/movies/'.$row['id']));

        $pipeline = new RowProcessingPipeline(
            baseMapper: static fn (array $row): array => ['id' => $row['id']],
            columns: [
                TextColumn::new('id'),
                ActionColumn::fromActions('actions', 'Actions', $actions),
            ],
            actionRowDataResolver: new ActionRowDataResolver(),
        );

        $mappedRow = $pipeline->map(['id' => 8]);

        $this->assertSame('/movies/8', $mappedRow['__ux_datatables_actions']['DETAIL']['url']);
    }

    #[Test]
    public function it_runs_map_then_template_then_action_resolution_in_order(): void
    {
        $actions = (new Actions())
            ->add(Action::detail()->linkToUrl(static fn (array $row): string => '/movies/'.$row['id']));

        $pipeline = new RowProcessingPipeline(
            baseMapper: static fn (array $row): array => [
                'id'     => $row['id'],
                'status' => 'mapped-'.$row['status'],
            ],
            columns: [
                TextColumn::new('id'),
                TemplateColumn::new('status_display')
                    ->setField('status')
                    ->setTemplate('datatable/columns/order.html.twig'),
                ActionColumn::fromActions('actions', 'Actions', $actions),
            ],
            templateColumnRenderer: new TemplateColumnRenderer(
                new Environment(new ArrayLoader([
                    'datatable/columns/order.html.twig' => '{{ row.__ux_datatables_actions.DETAIL.url|default("missing") }}|{{ data }}',
                ]))
            ),
            actionRowDataResolver: new ActionRowDataResolver(),
        );

        $mappedRow = $pipeline->map(['id' => 7, 'status' => 'active']);

        $this->assertSame('missing|mapped-active', $mappedRow['status']);
        $this->assertSame('/movies/7', $mappedRow['__ux_datatables_actions']['DETAIL']['url']);
    }
}

final readonly class TemplateRow
{
    public function __construct(
        public int $id,
        public string $status,
    ) {
    }
}
