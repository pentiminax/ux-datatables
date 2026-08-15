<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Column\Rendering\TemplateColumnRenderer;
use Pentiminax\UX\DataTables\Column\TemplateColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\RowMapper\RowContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[CoversClass(TemplateColumnRenderer::class)]
final class TemplateColumnRendererTest extends TestCase
{
    /**
     * @param array<string, string> $templates
     * @param list<ColumnInterface> $columns
     * @param array<string, mixed>  $row
     * @param array<string, mixed>  $expectedRow
     */
    #[Test]
    #[DataProvider('provideRenderedRows')]
    public function it_renders_template_columns(array $templates, array $columns, array $row, mixed $mappedRow, array $expectedRow): void
    {
        $rendered = self::renderer($templates)->renderRow(row: $row, mappedRow: $mappedRow, columns: $columns);

        $this->assertSame($expectedRow, $rendered);
    }

    /**
     * @return iterable<string, array{0: array<string, string>, 1: list<ColumnInterface>, 2: array<string, mixed>, 3: mixed, 4: array<string, mixed>}>
     */
    public static function provideRenderedRows(): iterable
    {
        yield 'entity field' => [
            ['status.html.twig' => '<span data-field="{{ column.field }}">{{ data }}</span>'],
            [
                TextColumn::new('id'),
                TemplateColumn::new('status_display')->setField('status')->setTemplate('status.html.twig'),
            ],
            ['id' => 7],
            new TemplateEntity(id: 7, status: 'active'),
            ['id' => 7, 'status_display' => '<span data-field="status">active</span>'],
        ];

        yield 'column matching the field name is not overwritten' => [
            ['margin.html.twig' => '<span class="badge">{{ data|number_format(2) }}</span>'],
            [
                TemplateColumn::new('margin')->setField('marginRate')->setTemplate('margin.html.twig'),
                TextColumn::new('marginRate'),
            ],
            ['margin' => 12.3456, 'marginRate' => 12.3456],
            [],
            ['margin' => '<span class="badge">12.35</span>', 'marginRate' => 12.3456],
        ];

        yield 'data key when no field is configured' => [
            ['column.html.twig' => '<b>{{ data }}</b>'],
            [TemplateColumn::new('status_display')->setData('status')->setTemplate('column.html.twig')],
            ['status' => 'active'],
            [],
            ['status' => '<b>active</b>'],
        ];

        yield 'nested field is keyed by name' => [
            ['column.html.twig' => '<b>{{ data }}</b>'],
            [TemplateColumn::new('author_name')->setField('author.name')->setTemplate('column.html.twig')],
            [],
            ['author'      => ['name' => 'Ada']],
            ['author_name' => '<b>Ada</b>'],
        ];

        yield 'column without a row key is skipped' => [
            ['column.html.twig' => '<b>{{ data }}</b>'],
            [TemplateColumn::new('')->setTemplate('column.html.twig')],
            ['id' => 3],
            [],
            ['id' => 3],
        ];

        yield 'row array value wins over mapped row' => [
            ['column.html.twig' => '{{ data }}'],
            [TemplateColumn::new('status_display')->setField('status')->setTemplate('column.html.twig')],
            ['status' => 'from_row'],
            new TemplateEntity(id: 1, status: 'from_entity'),
            ['status' => 'from_row', 'status_display' => 'from_row'],
        ];

        yield 'custom template parameters' => [
            ['column.html.twig' => '{{ badge_class }}: {{ data }}'],
            [
                TemplateColumn::new('status_display')
                    ->setField('status')
                    ->setTemplate('column.html.twig', ['badge_class' => 'badge-success']),
            ],
            ['status' => 'active'],
            [],
            ['status' => 'active', 'status_display' => 'badge-success: active'],
        ];

        yield 'multiple template columns in the same row' => [
            [
                'status.html.twig' => 'Status: {{ data }}',
                'type.html.twig'   => 'Type: {{ data }}',
            ],
            [
                TemplateColumn::new('status_display')->setField('status')->setTemplate('status.html.twig'),
                TemplateColumn::new('type_display')->setField('type')->setTemplate('type.html.twig'),
            ],
            ['status' => 'active', 'type' => 'admin'],
            [],
            [
                'status'         => 'active',
                'type'           => 'admin',
                'status_display' => 'Status: active',
                'type_display'   => 'Type: admin',
            ],
        ];

        yield 'entity, row and column exposed in the twig context' => [
            ['column.html.twig' => '{{ entity.getStatus() }}-{{ row.id }}-{{ column.name }}'],
            [TemplateColumn::new('status_display')->setField('status')->setTemplate('column.html.twig')],
            ['id' => 42],
            new TemplateEntity(id: 42, status: 'verified'),
            ['id' => 42, 'status_display' => 'verified-42-status_display'],
        ];

        // entity (back-compat) and item both resolve to the projected DTO; source stays the original.
        yield 'projected item and original source of a row context' => [
            ['column.html.twig' => '{{ item.getStatus() }}|{{ source.getStatus() }}|{{ entity.getStatus() }}'],
            [TemplateColumn::new('status_display')->setField('status')->setTemplate('column.html.twig')],
            ['id' => 1],
            new RowContext(new TemplateEntity(id: 1, status: 'raw'), new TemplateEntity(id: 1, status: 'projected')),
            ['id' => 1, 'status_display' => 'projected|raw|projected'],
        ];
    }

    /**
     * @param class-string<\Throwable> $expectedException
     */
    #[Test]
    #[DataProvider('provideUnrenderableTemplates')]
    public function it_fails_fast_when_the_template_cannot_be_rendered(TemplateColumnRenderer $renderer, string $expectedException, string $expectedMessage): void
    {
        $this->expectException($expectedException);
        $this->expectExceptionMessage($expectedMessage);

        $renderer->renderRow(
            row: ['status_display' => 'active'],
            mappedRow: ['status_display' => 'active'],
            columns: [TemplateColumn::new('status_display')->setTemplate('datatable/columns/missing.html.twig')]
        );
    }

    /**
     * @return iterable<string, array{0: TemplateColumnRenderer, 1: class-string<\Throwable>, 2: string}>
     */
    public static function provideUnrenderableTemplates(): iterable
    {
        yield 'missing twig environment' => [
            new TemplateColumnRenderer(),
            \LogicException::class,
            'Twig Environment is required to render TemplateColumn cells.',
        ];

        yield 'unknown template' => [
            self::renderer([]),
            LoaderError::class,
            'datatable/columns/missing.html.twig',
        ];
    }

    /**
     * @param array<string, string> $templates
     */
    private static function renderer(array $templates): TemplateColumnRenderer
    {
        return new TemplateColumnRenderer(new Environment(new ArrayLoader($templates)));
    }
}

final readonly class TemplateEntity
{
    public function __construct(
        private int $id,
        private string $status,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getStatus(): string
    {
        return $this->status;
    }
}
