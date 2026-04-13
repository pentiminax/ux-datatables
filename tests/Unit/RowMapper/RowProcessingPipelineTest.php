<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\RowMapper;

use Pentiminax\UX\DataTables\Column\ActionColumn;
use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\Rendering\ActionRowDataResolver;
use Pentiminax\UX\DataTables\Column\Rendering\TemplateColumnRenderer;
use Pentiminax\UX\DataTables\Column\TemplateColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\RowStageInterface;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\RowMapper\RowProcessingPipeline;
use Pentiminax\UX\DataTables\RowMapper\Stage\ActionResolutionStage;
use Pentiminax\UX\DataTables\RowMapper\Stage\NormalizationStage;
use Pentiminax\UX\DataTables\RowMapper\Stage\TemplateRenderingStage;
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
    public function it_applies_the_base_mapper_when_no_stage_is_added(): void
    {
        $pipeline = new RowProcessingPipeline(
            baseMapper: static fn (array $row): array => ['id' => $row['id']],
            columns: [TextColumn::new('id')],
        );

        $mappedRow = $pipeline->map(['id' => 9, 'title' => 'Alien']);

        $this->assertSame(['id' => 9], $mappedRow);
    }

    #[Test]
    public function add_returns_self_for_fluent_chaining(): void
    {
        $pipeline = new RowProcessingPipeline(
            baseMapper: static fn (array $row): array => $row,
            columns: [],
        );

        $stage = new class implements RowStageInterface {
            public function process(array $mappedRow, mixed $originalRow, array $columns): array
            {
                return $mappedRow;
            }
        };

        $this->assertSame($pipeline, $pipeline->add($stage));
    }

    #[Test]
    public function stages_are_applied_in_insertion_order(): void
    {
        $order    = [];
        $pipeline = new RowProcessingPipeline(
            baseMapper: static fn (array $row): array => $row,
            columns: [],
        );

        foreach (['first', 'second', 'third'] as $label) {
            $pipeline->add(new class($label, $order) implements RowStageInterface {
                public function __construct(
                    private readonly string $label,
                    private array &$order,
                ) {
                }

                public function process(array $mappedRow, mixed $originalRow, array $columns): array
                {
                    $this->order[] = $this->label;

                    return $mappedRow;
                }
            });
        }

        $pipeline->map([]);

        $this->assertSame(['first', 'second', 'third'], $order);
    }

    #[Test]
    public function it_renders_template_columns_via_template_rendering_stage(): void
    {
        $pipeline = (new RowProcessingPipeline(
            baseMapper: static fn (TemplateRow $row): array => ['id' => $row->id],
            columns: [
                TextColumn::new('id'),
                TemplateColumn::new('status_display')
                    ->setField('status')
                    ->setTemplate('datatable/columns/status_badge.html.twig'),
            ],
        ))->add(new NormalizationStage())
          ->add(new TemplateRenderingStage(
              new TemplateColumnRenderer(
                  new Environment(new ArrayLoader([
                      'datatable/columns/status_badge.html.twig' => '<span>{{ row.id }}-{{ data }}</span>',
                  ]))
              )
          ));

        $mappedRow = $pipeline->map(new TemplateRow(id: 5, status: 'active'));

        $this->assertSame([
            'id'     => 5,
            'status' => '<span>5-active</span>',
        ], $mappedRow);
    }

    #[Test]
    public function it_resolves_action_urls_via_action_resolution_stage(): void
    {
        $actions = (new Actions())
            ->add(Action::detail()->linkToUrl(static fn (array $row): string => '/movies/'.$row['id']));

        $pipeline = (new RowProcessingPipeline(
            baseMapper: static fn (array $row): array => ['id' => $row['id']],
            columns: [
                TextColumn::new('id'),
                ActionColumn::fromActions('actions', 'Actions', $actions),
            ],
        ))->add(new ActionResolutionStage(new ActionRowDataResolver()));

        $mappedRow = $pipeline->map(['id' => 8]);

        $this->assertSame('/movies/8', $mappedRow['__ux_datatables_actions']['DETAIL']['url']);
    }

    #[Test]
    public function it_resolves_relation_object_via_dotted_field_path(): void
    {
        $client = new class {
            public function getName(): string
            {
                return 'Acme Corp';
            }
        };

        $pipeline = (new RowProcessingPipeline(
            baseMapper: static fn (mixed $row): array => ['client' => $row],
            columns: [TextColumn::new('client', 'Client')->setField('client.name')],
        ))->add(new NormalizationStage());

        $mappedRow = $pipeline->map($client);

        $this->assertSame(['client' => 'Acme Corp'], $mappedRow);
    }

    #[Test]
    public function it_converts_stringable_object_to_string(): void
    {
        $stringable = new class implements \Stringable {
            public function __toString(): string
            {
                return 'My Label';
            }
        };

        $pipeline = (new RowProcessingPipeline(
            baseMapper: static fn (mixed $row): array => ['label' => $row],
            columns: [TextColumn::new('label', 'Label')],
        ))->add(new NormalizationStage());

        $mappedRow = $pipeline->map($stringable);

        $this->assertSame(['label' => 'My Label'], $mappedRow);
    }

    #[Test]
    public function it_converts_non_stringable_object_to_null(): void
    {
        $pipeline = (new RowProcessingPipeline(
            baseMapper: static fn (mixed $row): array => ['client' => $row],
            columns: [TextColumn::new('client', 'Client')],
        ))->add(new NormalizationStage());

        $mappedRow = $pipeline->map(new \stdClass());

        $this->assertNull($mappedRow['client']);
    }

    #[Test]
    public function it_formats_datetime_values_using_date_column(): void
    {
        $date = new \DateTimeImmutable('2024-03-15');

        $pipeline = (new RowProcessingPipeline(
            baseMapper: static fn (mixed $row): array => ['createdAt' => $row],
            columns: [DateColumn::new('createdAt', 'Date')->setFormat('d/m/Y')],
        ))->add(new NormalizationStage());

        $mappedRow = $pipeline->map($date);

        $this->assertSame(['createdAt' => '15/03/2024'], $mappedRow);
    }

    #[Test]
    public function it_leaves_scalar_values_unchanged(): void
    {
        $pipeline = (new RowProcessingPipeline(
            baseMapper: static fn (array $row): array => $row,
            columns: [TextColumn::new('title', 'Title')],
        ))->add(new NormalizationStage());

        $mappedRow = $pipeline->map(['title' => 'Hello World']);

        $this->assertSame(['title' => 'Hello World'], $mappedRow);
    }

    #[Test]
    public function it_runs_stages_in_order_normalization_then_template_then_action(): void
    {
        $actions = (new Actions())
            ->add(Action::detail()->linkToUrl(static fn (array $row): string => '/movies/'.$row['id']));

        $pipeline = (new RowProcessingPipeline(
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
        ))->add(new NormalizationStage())
          ->add(new TemplateRenderingStage(
              new TemplateColumnRenderer(
                  new Environment(new ArrayLoader([
                      'datatable/columns/order.html.twig' => '{{ row.__ux_datatables_actions.DETAIL.url|default("missing") }}|{{ data }}',
                  ]))
              )
          ))
          ->add(new ActionResolutionStage(new ActionRowDataResolver()));

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
