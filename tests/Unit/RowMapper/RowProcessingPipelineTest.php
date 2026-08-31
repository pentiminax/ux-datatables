<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\RowMapper;

use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\Rendering\ActionRowDataResolver;
use Pentiminax\UX\DataTables\Column\Rendering\UrlColumnDataResolver;
use Pentiminax\UX\DataTables\Column\TemplateColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Column\UrlColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\RowStageInterface;
use Pentiminax\UX\DataTables\RowMapper\DefaultRowMapper;
use Pentiminax\UX\DataTables\RowMapper\RowProcessingPipeline;
use Pentiminax\UX\DataTables\RowMapper\Stage\NormalizationStage;
use Pentiminax\UX\DataTables\Security\PermissionChecker;
use Pentiminax\UX\DataTables\Tests\Support\BuildsRowStageContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @internal
 */
#[CoversClass(RowProcessingPipeline::class)]
final class RowProcessingPipelineTest extends TestCase
{
    use BuildsRowStageContext;

    #[Test]
    public function it_applies_the_base_mapper_then_every_stage_in_insertion_order(): void
    {
        $pipeline = new RowProcessingPipeline(
            baseMapper: static fn (array $row): array => ['trace' => 'base:'.$row['id']],
            columns: [TextColumn::new('trace')],
        );

        $this->assertSame($pipeline, $pipeline->add(self::tracingStage('first')));

        $pipeline->add(self::tracingStage('second'))->add(self::tracingStage('third'));

        $mappedRow = $pipeline->map(['id' => 9, 'title' => 'Alien']);

        $this->assertSame(['trace' => 'base:9|first|second|third'], $mappedRow);
    }

    #[Test]
    public function it_strips_denied_column_values_and_keeps_unrelated_keys(): void
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturnCallback(
            static fn (mixed $attribute): bool => 'ROLE_HR' !== $attribute
        );

        $pipeline = new RowProcessingPipeline(
            baseMapper: static fn (array $row): array => $row,
            columns: [
                TextColumn::new('salary', 'Salary')->permission('ROLE_HR'),
                TextColumn::new('name', 'Name'),
            ],
            columnResolver: new ColumnResolver(permissionChecker: new PermissionChecker($checker)),
        );

        $this->assertSame(
            ['name' => 'Ada', 'extra' => 'kept'],
            $pipeline->map(['salary' => 120000, 'name' => 'Ada', 'extra' => 'kept']),
        );
    }

    #[Test]
    public function it_strips_denied_nested_dotted_paths_from_array_rows(): void
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturnCallback(
            static fn (mixed $attribute): bool => 'ROLE_HR' !== $attribute
        );

        $pipeline = new RowProcessingPipeline(
            baseMapper: static fn (array $row): array => $row,
            columns: [
                TextColumn::new('user.email', 'Email')->permission('ROLE_HR'),
                TextColumn::new('name', 'Name'),
            ],
            columnResolver: new ColumnResolver(permissionChecker: new PermissionChecker($checker)),
        );

        $this->assertSame(
            ['user' => ['role' => 'admin'], 'name' => 'Ada'],
            $pipeline->map(['user' => ['email' => 'secret', 'role' => 'admin'], 'name' => 'Ada']),
        );
    }

    #[Test]
    public function it_strips_denied_nested_paths_from_object_backed_array_rows(): void
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturnCallback(
            static fn (mixed $attribute): bool => 'ROLE_HR' !== $attribute
        );

        $pipeline = new RowProcessingPipeline(
            baseMapper: static fn (array $row): array => $row,
            columns: [
                TextColumn::new('value.name', 'Name')->permission('ROLE_HR'),
                TextColumn::new('name', 'Name'),
            ],
            columnResolver: new ColumnResolver(permissionChecker: new PermissionChecker($checker)),
        );

        $this->assertSame(
            ['value' => ['role' => 'admin'], 'name' => 'Ada'],
            $pipeline->map(['value' => (object) ['name' => 'secret', 'role' => 'admin'], 'name' => 'Ada']),
        );
    }

    #[Test]
    #[DataProvider('normalizedValueProvider')]
    public function it_normalizes_the_base_mapper_output_through_the_normalization_stage(mixed $row, ColumnInterface $column, mixed $expected): void
    {
        $pipeline = (new RowProcessingPipeline(
            baseMapper: static fn (mixed $row): array => ['value' => $row],
            columns: [$column],
        ))->add(new NormalizationStage());

        $mappedRow = $pipeline->map($row);

        $this->assertSame(['value' => $expected], $mappedRow);
    }

    public static function normalizedValueProvider(): iterable
    {
        $client = new class {
            public function getName(): string
            {
                return 'Acme Corp';
            }
        };

        $stringable = new class implements \Stringable {
            public function __toString(): string
            {
                return 'My Label';
            }
        };

        yield 'relation object resolved via dotted field path' => [
            $client,
            TextColumn::new('value', 'Client')->setField('value.name'),
            'Acme Corp',
        ];

        yield 'stringable object converted to string' => [
            $stringable,
            TextColumn::new('value', 'Label'),
            'My Label',
        ];

        yield 'non stringable object converted to null' => [
            new \stdClass(),
            TextColumn::new('value', 'Client'),
            null,
        ];

        yield 'datetime formatted by the date column' => [
            new \DateTimeImmutable('2024-03-15'),
            DateColumn::new('value', 'Date')->setFormat('d/m/Y'),
            '15/03/2024',
        ];

        yield 'scalar value left unchanged' => [
            'Hello World',
            TextColumn::new('value', 'Title'),
            'Hello World',
        ];
    }

    /**
     * @param \Closure(list<ColumnInterface>): \Closure $baseMapperFactory
     * @param list<ColumnInterface>                     $columns
     * @param array<string, mixed>                      $expected
     */
    #[Test]
    #[DataProvider('actionRowProvider')]
    public function it_resolves_action_urls(\Closure $baseMapperFactory, array $columns, mixed $row, array $expected): void
    {
        $pipeline = new RowProcessingPipeline(
            baseMapper: $baseMapperFactory($columns),
            columns: $columns,
        );

        $mappedRow = $pipeline->map($row);

        $this->assertSame($expected, $mappedRow);
    }

    public static function actionRowProvider(): iterable
    {
        yield 'array row mapped by a custom base mapper' => [
            static fn (array $columns): \Closure => static fn (array $row): array => ['id' => $row['id']],
            [
                TextColumn::new('id'),
                self::detailActionColumn(static fn (array $row): string => '/movies/'.$row['id']),
            ],
            ['id' => 8],
            [
                'id'                                   => 8,
                ActionRowDataResolver::ROW_ACTIONS_KEY => ['DETAIL' => ['url' => '/movies/8']],
            ],
        ];

        yield 'object row mapped by the default row mapper' => [
            static fn (array $columns): \Closure => (new DefaultRowMapper($columns))->map(...),
            [
                TextColumn::new('title', 'Title'),
                self::detailActionColumn(static fn (TemplateRow $row): string => '/movies/'.$row->id),
            ],
            new TemplateRow(id: 42, title: 'Alien', status: 'active'),
            [
                'title'                                => 'Alien',
                ActionRowDataResolver::ROW_ACTIONS_KEY => ['DETAIL' => ['url' => '/movies/42']],
            ],
        ];

        yield 'row without action column is passed through' => [
            static fn (array $columns): \Closure => static fn (array $row): array => ['id' => $row['id']],
            [TextColumn::new('id')],
            ['id' => 3],
            ['id' => 3],
        ];
    }

    #[Test]
    public function it_resolves_url_columns(): void
    {
        $pipeline = new RowProcessingPipeline(
            baseMapper: static fn (array $row): array => ['website' => $row['label']],
            columns: [
                UrlColumn::new('website')
                    ->linkToUrl(static fn (array $row): string => 'https://example.com/users/'.$row['slug']),
            ],
        );

        $mappedRow = $pipeline->map(['label' => 'Profile', 'slug' => 'jane']);

        $this->assertSame([
            'website'                           => 'Profile',
            UrlColumnDataResolver::ROW_URLS_KEY => ['website' => 'https://example.com/users/jane'],
        ], $mappedRow);
    }

    /**
     * @param array<string, mixed>  $mappedRow
     * @param list<ColumnInterface> $columns
     * @param array<string, mixed>  $expected
     */
    #[Test]
    #[DataProvider('renderedRowProvider')]
    public function it_renders_template_columns_and_leaves_the_other_columns_alone(array $mappedRow, array $columns, array $expected): void
    {
        $pipeline = new RowProcessingPipeline(
            baseMapper: static fn (array $row): array => $row,
            columns: $columns,
            templateColumnRenderer: self::templateColumnRenderer(['badge.html.twig' => '<b>{{ data }}</b>']),
        );

        $this->assertSame($expected, $pipeline->map($mappedRow));
    }

    public static function renderedRowProvider(): iterable
    {
        yield 'template column is rendered next to its source value' => [
            ['status' => 'active'],
            [TemplateColumn::new('status_display')->setField('status')->setTemplate('badge.html.twig')],
            ['status' => 'active', 'status_display' => '<b>active</b>'],
        ];

        yield 'non template column is passed through' => [
            ['id' => 42],
            [TextColumn::new('id')],
            ['id' => 42],
        ];
    }

    #[Test]
    public function it_runs_normalization_then_template_rendering_then_action_resolution(): void
    {
        $pipeline = (new RowProcessingPipeline(
            baseMapper: static fn (TemplateRow $row): array => [
                'id'     => $row->id,
                'status' => 'mapped-'.$row->status,
            ],
            columns: [
                TextColumn::new('id'),
                TemplateColumn::new('status_display')
                    ->setField('status')
                    ->setTemplate('datatable/columns/order.html.twig'),
                self::detailActionColumn(static fn (TemplateRow $row): string => '/movies/'.$row->id),
            ],
            templateColumnRenderer: self::templateColumnRenderer([
                'datatable/columns/order.html.twig' => '{{ row.__ux_datatables_actions.DETAIL.url|default("missing") }}|{{ row.id }}-{{ data }}',
            ]),
        ))->add(new NormalizationStage());

        $mappedRow = $pipeline->map(new TemplateRow(id: 7, title: 'Alien', status: 'active'));

        $this->assertSame([
            'id'                                   => 7,
            'status'                               => 'mapped-active',
            'status_display'                       => 'missing|7-mapped-active',
            ActionRowDataResolver::ROW_ACTIONS_KEY => ['DETAIL' => ['url' => '/movies/7']],
        ], $mappedRow);
    }

    /**
     * A stage appending its label to the row, so insertion order is observable in the payload.
     */
    private static function tracingStage(string $label): RowStageInterface
    {
        return new class($label) implements RowStageInterface {
            public function __construct(
                private readonly string $label,
            ) {
            }

            public function process(array $mappedRow, mixed $originalRow, array $columns): array
            {
                $mappedRow['trace'] .= '|'.$this->label;

                return $mappedRow;
            }
        };
    }
}

final readonly class TemplateRow
{
    public function __construct(
        public int $id,
        public string $title,
        public string $status,
    ) {
    }
}
