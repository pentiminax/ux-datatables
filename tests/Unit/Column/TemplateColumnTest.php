<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Column\TemplateColumn;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(TemplateColumn::class)]
final class TemplateColumnTest extends TestCase
{
    #[Test]
    public function it_serializes_template_path(): void
    {
        $column = TemplateColumn::new('status_display')
            ->setField('status')
            ->setTemplate('datatable/columns/status_badge.html.twig');

        $data = $column->jsonSerialize();

        $this->assertSame('status_display', $data['name']);
        $this->assertSame('status_display', $data['data']);
        $this->assertSame('status', $data['field']);
        $this->assertSame('html', $data['type']);
        $this->assertSame('datatable/columns/status_badge.html.twig', $data['customOptions']['templatePath']);
    }

    #[Test]
    public function it_rejects_empty_template_path(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Template path cannot be empty.');

        TemplateColumn::new('status_display')->setTemplate('   ');
    }

    #[Test]
    public function it_fails_when_template_is_missing(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Template path is not configured for column');

        TemplateColumn::new('status_display')->getTemplate();
    }

    #[Test]
    public function it_returns_empty_array_as_default_parameters(): void
    {
        $column = TemplateColumn::new('status_display');

        $this->assertSame([], $column->getTemplateParameters());
    }

    #[Test]
    public function it_stores_template_parameters(): void
    {
        $column = TemplateColumn::new('status_display')
            ->setTemplate('some/template.html.twig', ['badge_class' => 'badge-success', 'show_icon' => true]);

        $this->assertSame(['badge_class' => 'badge-success', 'show_icon' => true], $column->getTemplateParameters());
    }

    #[Test]
    public function it_does_not_serialize_template_parameters(): void
    {
        $column = TemplateColumn::new('status_display')
            ->setTemplate('some/template.html.twig', ['secret' => 'server-side-only']);

        $data = $column->jsonSerialize();

        $this->assertArrayNotHasKey('templateParameters', $data);
        $this->assertArrayNotHasKey(TemplateColumn::OPTION_TEMPLATE_PARAMETERS, $data);
        $this->assertArrayNotHasKey('templateParameters', $data['customOptions'] ?? []);
    }

    #[Test]
    public function it_is_fluent(): void
    {
        $column = TemplateColumn::new('status_display');

        $this->assertSame($column, $column->setTemplate('some/template.html.twig'));
    }
}
