<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Form;

use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Form\EditModalTemplateResolver;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 */
#[CoversClass(EditModalTemplateResolver::class)]
final class EditModalTemplateResolverTest extends TestCase
{
    /**
     * @param class-string<AbstractDataTable> $dataTableClass
     */
    #[Test]
    #[DataProvider('chromeTemplateProvider')]
    public function it_resolves_the_chrome_template_of_a_registered_data_table(string $expected, string $dataTableClass, callable $factory): void
    {
        $resolver = $this->createResolver([$dataTableClass => $factory]);

        $this->assertSame($expected, $resolver->resolveChromeTemplate($dataTableClass));
    }

    public static function chromeTemplateProvider(): \Generator
    {
        yield 'fluent template wins over the attribute' => [
            'fluent.html.twig',
            FluentEditModalDataTable::class,
            static fn (): FluentEditModalDataTable => new FluentEditModalDataTable(),
        ];

        yield 'attribute template without a fluent override' => [
            'attribute.html.twig',
            AttributeEditModalDataTable::class,
            static fn (): AttributeEditModalDataTable => new AttributeEditModalDataTable(),
        ];

        yield 'bundle default without any override' => [
            '@DataTables/modal/datatables/edit_modal.html.twig',
            DefaultEditModalDataTable::class,
            static fn (): DefaultEditModalDataTable => new DefaultEditModalDataTable(),
        ];
    }

    #[Test]
    public function it_returns_the_default_template_for_non_whitelisted_classes(): void
    {
        $resolver = $this->createResolver();

        $template = $resolver->resolveChromeTemplate(AttributeEditModalDataTable::class);

        $this->assertSame('@DataTables/modal/datatables/edit_modal.html.twig', $template);
    }

    #[Test]
    public function it_resolves_the_body_template_from_bundle_config(): void
    {
        $resolver = $this->createResolver();

        $template = $resolver->resolveBodyTemplate();

        $this->assertSame('@DataTables/modal/datatables/_form_body.html.twig', $template);
    }

    #[Test]
    public function it_resolves_columns_from_a_registered_data_table(): void
    {
        $resolver = $this->createResolver([
            ColumnsEditModalDataTable::class => static fn (): ColumnsEditModalDataTable => new ColumnsEditModalDataTable(),
        ]);

        $columns = $resolver->resolveColumns(ColumnsEditModalDataTable::class);

        $this->assertSame(['name'], array_map(static fn (ColumnInterface $column): string => $column->getName(), $columns));
    }

    #[Test]
    public function it_throws_when_resolving_columns_for_an_unregistered_class(): void
    {
        $resolver = $this->createResolver();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/not registered/');

        $resolver->resolveColumns(DefaultEditModalDataTable::class);
    }

    /**
     * @param array<class-string<AbstractDataTable>, callable(): AbstractDataTable> $factories
     */
    private function createResolver(array $factories = []): EditModalTemplateResolver
    {
        return new EditModalTemplateResolver(
            new ServiceLocator($factories),
            '@DataTables/modal/datatables/edit_modal.html.twig',
            '@DataTables/modal/datatables/_form_body.html.twig',
        );
    }
}

#[AsDataTable(entityClass: \stdClass::class, editModalTemplate: 'attribute.html.twig')]
final class AttributeEditModalDataTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        return [];
    }
}

#[AsDataTable(entityClass: \stdClass::class, editModalTemplate: 'attribute.html.twig')]
final class FluentEditModalDataTable extends AbstractDataTable
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->editModalTemplate('fluent.html.twig');
    }

    public function configureColumns(): iterable
    {
        return [];
    }
}

final class DefaultEditModalDataTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        return [];
    }
}

#[AsDataTable(entityClass: \stdClass::class)]
final class ColumnsEditModalDataTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield TextColumn::new('name', 'Name');
    }
}
