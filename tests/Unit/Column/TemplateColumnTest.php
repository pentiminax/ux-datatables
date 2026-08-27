<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Column\TemplateColumn;
use Pentiminax\UX\DataTables\Tests\Support\DataTableTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * @internal
 */
#[CoversClass(TemplateColumn::class)]
final class TemplateColumnTest extends DataTableTestCase
{
    #[Test]
    public function it_serializes_template_path(): void
    {
        $column = TemplateColumn::new('status_display')
            ->setField('status')
            ->setTemplate('datatable/columns/status_badge.html.twig');

        $this->assertColumnHeader($column, 'html', 'status_display', 'status_display');
        $this->assertSame('status', $column->jsonSerialize()['field']);
        $this->assertCustomOption('datatable/columns/status_badge.html.twig', 'templatePath', $column);
    }

    /**
     * @param class-string<\Throwable> $expectedException
     */
    #[Test]
    #[DataProvider('provideInvalidTemplateConfigurations')]
    public function it_rejects_an_invalid_template_configuration(callable $scenario, string $expectedException, string $expectedMessage): void
    {
        $this->expectException($expectedException);
        $this->expectExceptionMessage($expectedMessage);

        $scenario();
    }

    /**
     * @return iterable<string, array{0: callable(): mixed, 1: class-string<\Throwable>, 2: string}>
     */
    public static function provideInvalidTemplateConfigurations(): iterable
    {
        yield 'empty path' => [
            static fn () => TemplateColumn::new('status_display')->setTemplate('   '),
            \InvalidArgumentException::class,
            'Template path cannot be empty.',
        ];

        yield 'missing path' => [
            static fn () => TemplateColumn::new('status_display')->getTemplate(),
            \LogicException::class,
            'Template path is not configured for column',
        ];

        yield 'reserved parameter' => [
            static fn () => TemplateColumn::new('status_display')->setTemplate('column.html.twig', ['payload' => []]),
            \InvalidArgumentException::class,
            'Template parameters "payload" are reserved by the renderer and cannot be overridden on column "status_display".',
        ];

        yield 'several reserved parameters are all reported' => [
            static fn () => TemplateColumn::new('status_display')->setTemplate('column.html.twig', [
                'row'         => 1,
                'badge_class' => 'badge-success',
                'entity'      => 2,
            ]),
            \InvalidArgumentException::class,
            'Template parameters "row", "entity" are reserved',
        ];
    }

    #[Test]
    public function it_keeps_template_parameters_server_side(): void
    {
        $column = TemplateColumn::new('status_display');

        $this->assertSame([], $column->getTemplateParameters());

        $column->setTemplate('some/template.html.twig', ['badge_class' => 'badge-success', 'show_icon' => true, 'item' => 'custom']);

        $this->assertSame(['badge_class' => 'badge-success', 'show_icon' => true, 'item' => 'custom'], $column->getTemplateParameters());

        $data = $column->jsonSerialize();

        $this->assertArrayNotHasKey('templateParameters', $data);
        $this->assertArrayNotHasKey(TemplateColumn::OPTION_TEMPLATE_PARAMETERS, $data);
        $this->assertNoCustomOption('templateParameters', $column);
    }

    #[Test]
    public function it_is_display_only_by_default(): void
    {
        $column = TemplateColumn::new('status_display');
        $data   = $column->jsonSerialize();

        $this->assertFalse($column->isOrderable());
        $this->assertFalse($column->isSearchable());
        $this->assertFalse($column->isGlobalSearchable());
        $this->assertFalse($data['orderable']);
        $this->assertFalse($data['searchable']);
    }

    #[Test]
    public function it_allows_re_enabling_query_behavior(): void
    {
        $column = TemplateColumn::new('email')
            ->setField('email')
            ->setOrderable(true)
            ->setSearchable(true);

        $this->assertTrue($column->isOrderable());
        $this->assertTrue($column->isSearchable());
    }
}
