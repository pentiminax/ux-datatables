<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Column\TemplateColumn;
use PHPUnit\Framework\TestCase;

final class TemplateColumnTest extends TestCase
{
    public function testItSerializesTemplatePath(): void
    {
        $column = TemplateColumn::new('status_display')
            ->setField('status')
            ->setTemplate('datatable/columns/status_badge.html.twig');

        $data = $column->jsonSerialize();

        $this->assertSame('status_display', $data['name']);
        $this->assertSame('status_display', $data['data']);
        $this->assertSame('status', $data['field']);
        $this->assertSame('html', $data['type']);
        $this->assertSame('datatable/columns/status_badge.html.twig', $data['templatePath']);
    }

    public function testSetTemplateRejectsEmptyPath(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Template path cannot be empty.');

        TemplateColumn::new('status_display')->setTemplate('   ');
    }

    public function testGetTemplateFailsWhenMissing(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Template path is not configured for column');

        TemplateColumn::new('status_display')->getTemplate();
    }
}
