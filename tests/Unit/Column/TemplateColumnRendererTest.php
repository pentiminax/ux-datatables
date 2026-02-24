<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Column\TemplateColumn;
use Pentiminax\UX\DataTables\Column\TemplateColumnRenderer;
use Pentiminax\UX\DataTables\Column\TextColumn;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Loader\ArrayLoader;

final class TemplateColumnRendererTest extends TestCase
{
    public function testItRendersTemplateColumnFromEntityField(): void
    {
        $twig = new Environment(new ArrayLoader([
            'datatable/columns/status_badge.html.twig' => '<span data-field="{{ column.field }}">{{ value }}</span>',
        ]));

        $renderer = new TemplateColumnRenderer($twig);
        $columns  = [
            TextColumn::new('id'),
            TemplateColumn::new('status_display')->setField('status')->setTemplate('datatable/columns/status_badge.html.twig'),
        ];

        $row = $renderer->renderRow(
            row: ['id' => 7],
            entity: new TemplateEntity(id: 7, status: 'active'),
            columns: $columns
        );

        $this->assertSame(7, $row['id']);
        $this->assertSame('<span data-field="status">active</span>', $row['status_display']);
    }

    public function testItPassesExpectedContextInInlineRendering(): void
    {
        $twig = new Environment(new ArrayLoader([
            'datatable/columns/status_badge.html.twig' => '<span>{{ row.id }}-{{ entity.id }}-{{ column.name }}-{{ value }}</span>',
        ]));

        $renderer = new TemplateColumnRenderer($twig);
        $rows     = $renderer->renderInlineData(
            rows: [
                ['id' => 42, 'status' => 'inactive'],
            ],
            columns: [
                ['name' => 'id', 'data' => 'id'],
                [
                    'name'         => 'status_display',
                    'data'         => 'status_display',
                    'field'        => 'status',
                    'templatePath' => 'datatable/columns/status_badge.html.twig',
                ],
            ]
        );

        $this->assertSame('<span>42-42-status_display-inactive</span>', $rows[0]['status_display']);
    }

    public function testItFailsFastWhenTwigIsMissing(): void
    {
        $renderer = new TemplateColumnRenderer();
        $columns  = [
            TemplateColumn::new('status_display')->setTemplate('datatable/columns/status_badge.html.twig'),
        ];

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Twig Environment is required to render TemplateColumn cells.');

        $renderer->renderRow(
            row: ['status_display' => 'active'],
            entity: ['status_display' => 'active'],
            columns: $columns
        );
    }

    public function testItFailsFastWhenTemplateDoesNotExist(): void
    {
        $renderer = new TemplateColumnRenderer(new Environment(new ArrayLoader([])));
        $columns  = [
            TemplateColumn::new('status_display')->setTemplate('datatable/columns/missing.html.twig'),
        ];

        $this->expectException(LoaderError::class);

        $renderer->renderRow(
            row: ['status_display' => 'active'],
            entity: ['status_display' => 'active'],
            columns: $columns
        );
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
