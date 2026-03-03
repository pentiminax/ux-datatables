<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Column\TemplateColumn;
use Pentiminax\UX\DataTables\Column\TemplateColumnRenderer;
use Pentiminax\UX\DataTables\Column\TextColumn;
use PHPUnit\Framework\Attributes\CoversClass;
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
    #[Test]
    public function it_renders_column_from_entity_field(): void
    {
        $twig = new Environment(new ArrayLoader([
            'datatable/columns/status_badge.html.twig' => '<span data-field="{{ column.field }}">{{ data }}</span>',
        ]));

        $renderer = new TemplateColumnRenderer($twig);
        $columns  = [
            TextColumn::new('id'),
            TemplateColumn::new('status_display')->setField('status')->setTemplate('datatable/columns/status_badge.html.twig'),
        ];

        $row = $renderer->renderRow(
            row: ['id' => 7],
            mappedRow: new TemplateEntity(id: 7, status: 'active'),
            columns: $columns
        );

        $this->assertSame(7, $row['id']);
        $this->assertSame('<span data-field="status">active</span>', $row['status']);
    }

    #[Test]
    public function it_fails_fast_when_twig_is_missing(): void
    {
        $renderer = new TemplateColumnRenderer();
        $columns  = [
            TemplateColumn::new('status_display')->setTemplate('datatable/columns/status_badge.html.twig'),
        ];

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Twig Environment is required to render TemplateColumn cells.');

        $renderer->renderRow(
            row: ['status_display' => 'active'],
            mappedRow: ['status_display' => 'active'],
            columns: $columns
        );
    }

    #[Test]
    public function it_fails_fast_when_template_does_not_exist(): void
    {
        $renderer = new TemplateColumnRenderer(new Environment(new ArrayLoader([])));
        $columns  = [
            TemplateColumn::new('status_display')->setTemplate('datatable/columns/missing.html.twig'),
        ];

        $this->expectException(LoaderError::class);

        $renderer->renderRow(
            row: ['status_display' => 'active'],
            mappedRow: ['status_display' => 'active'],
            columns: $columns
        );
    }

    #[Test]
    public function it_prefers_row_array_value_over_mapped_row(): void
    {
        $twig = new Environment(new ArrayLoader([
            'column.html.twig' => '{{ data }}',
        ]));

        $renderer = new TemplateColumnRenderer($twig);
        $columns  = [
            TemplateColumn::new('status_display')->setField('status')->setTemplate('column.html.twig'),
        ];

        $row = $renderer->renderRow(
            row: ['status' => 'from_row'],
            mappedRow: new TemplateEntity(id: 1, status: 'from_entity'),
            columns: $columns
        );

        $this->assertSame('from_row', $row['status']);
    }

    #[Test]
    public function it_passes_custom_template_parameters_to_twig(): void
    {
        $twig = new Environment(new ArrayLoader([
            'column.html.twig' => '{{ badge_class }}: {{ data }}',
        ]));

        $renderer = new TemplateColumnRenderer($twig);
        $columns  = [
            TemplateColumn::new('status_display')
                ->setField('status')
                ->setTemplate('column.html.twig', ['badge_class' => 'badge-success']),
        ];

        $row = $renderer->renderRow(
            row: ['status' => 'active'],
            mappedRow: [],
            columns: $columns
        );

        $this->assertSame('badge-success: active', $row['status']);
    }

    #[Test]
    public function it_renders_multiple_template_columns_in_same_row(): void
    {
        $twig = new Environment(new ArrayLoader([
            'status.html.twig' => 'Status: {{ data }}',
            'type.html.twig'   => 'Type: {{ data }}',
        ]));

        $renderer = new TemplateColumnRenderer($twig);
        $columns  = [
            TemplateColumn::new('status_display')->setField('status')->setTemplate('status.html.twig'),
            TemplateColumn::new('type_display')->setField('type')->setTemplate('type.html.twig'),
        ];

        $row = $renderer->renderRow(
            row: ['status' => 'active', 'type' => 'admin'],
            mappedRow: [],
            columns: $columns
        );

        $this->assertSame('Status: active', $row['status']);
        $this->assertSame('Type: admin', $row['type']);
    }

    #[Test]
    public function it_exposes_entity_row_and_column_in_twig_context(): void
    {
        $twig = new Environment(new ArrayLoader([
            'column.html.twig' => '{{ entity.getStatus() }}-{{ row.id }}-{{ column.name }}',
        ]));

        $renderer = new TemplateColumnRenderer($twig);
        $columns  = [
            TemplateColumn::new('status_display')->setField('status')->setTemplate('column.html.twig'),
        ];

        $entity = new TemplateEntity(id: 42, status: 'verified');

        $row = $renderer->renderRow(
            row: ['id' => 42],
            mappedRow: $entity,
            columns: $columns
        );

        $this->assertSame('verified-42-status_display', $row['status']);
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
