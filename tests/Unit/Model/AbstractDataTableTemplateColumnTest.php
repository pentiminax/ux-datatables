<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Column\TemplateColumn;
use Pentiminax\UX\DataTables\Column\TemplateColumnRenderer;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class AbstractDataTableTemplateColumnTest extends TestCase
{
    public function testRowMapperPipelineRendersTemplateColumnWhenMapRowIsOverridden(): void
    {
        $renderer = new TemplateColumnRenderer(new Environment(new ArrayLoader([
            'datatable/columns/status_badge.html.twig' => '<span>{{ row.id }}-{{ data }}</span>',
        ])));

        $table = new TemplatePipelineTable($renderer);

        $row = $table->mapViaPipeline(new TemplatePipelineEntity(id: 9, status: 'active'));

        $this->assertSame([
            'id'             => 9,
            'status_display' => '<span>9-active</span>',
        ], $row);
    }
}

final class TemplatePipelineTable extends AbstractDataTable
{
    public function __construct(TemplateColumnRenderer $renderer)
    {
        parent::__construct(
            templateColumnRenderer: $renderer
        );
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
        yield TemplateColumn::new('status_display')
            ->setField('status')
            ->setTemplate('datatable/columns/status_badge.html.twig');
    }

    protected function mapRow(mixed $row): array
    {
        return ['id' => $row->getId()];
    }

    public function mapViaPipeline(mixed $row): array
    {
        return $this->rowMapper()->map($row);
    }
}

final readonly class TemplatePipelineEntity
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
