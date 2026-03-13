<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Column\TemplateColumn;
use Pentiminax\UX\DataTables\Column\TemplateColumnRenderer;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Runtime\DataTableRuntimeFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[CoversClass(AbstractDataTable::class)]
final class AbstractDataTableTemplateColumnTest extends TestCase
{
    #[Test]
    public function it_renders_template_column_when_map_row_is_overridden(): void
    {
        $renderer = new TemplateColumnRenderer(new Environment(new ArrayLoader([
            'datatable/columns/status_badge.html.twig' => '<span>{{ row.id }}-{{ data }}</span>',
        ])));

        $table = new TemplatePipelineTable($renderer);

        $row = $table->mapViaPipeline(new TemplatePipelineEntity(id: 9, status: 'active'));

        $this->assertSame([
            'id'     => 9,
            'status' => '<span>9-active</span>',
        ], $row);
    }
}

final class TemplatePipelineTable extends AbstractDataTable
{
    public function __construct(TemplateColumnRenderer $renderer)
    {
        parent::__construct(
            runtimeFactory: new DataTableRuntimeFactory(
                templateColumnRenderer: $renderer,
            ),
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
        return $this->createRowMapper()->map($row);
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
