<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Form;

use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Form\EditModalTemplateResolver;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * @internal
 */
#[CoversClass(EditModalTemplateResolver::class)]
final class EditModalTemplateResolverTest extends TestCase
{
    #[Test]
    public function it_uses_the_fluent_template_before_the_attribute_template(): void
    {
        $resolver = $this->createResolver([
            FluentEditModalDataTable::class => new FluentEditModalDataTable(),
        ]);

        $template = $resolver->resolveChromeTemplate(FluentEditModalDataTable::class);

        $this->assertSame('fluent.html.twig', $template);
    }

    #[Test]
    public function it_falls_back_to_the_attribute_template(): void
    {
        $resolver = $this->createResolver([
            AttributeEditModalDataTable::class => new AttributeEditModalDataTable(),
        ]);

        $template = $resolver->resolveChromeTemplate(AttributeEditModalDataTable::class);

        $this->assertSame('attribute.html.twig', $template);
    }

    #[Test]
    public function it_falls_back_to_the_bundle_default_when_no_override_exists(): void
    {
        $resolver = $this->createResolver([
            DefaultEditModalDataTable::class => new DefaultEditModalDataTable(),
        ]);

        $template = $resolver->resolveChromeTemplate(DefaultEditModalDataTable::class);

        $this->assertSame('@DataTables/modal/bootstrap5/edit_modal.html.twig', $template);
    }

    #[Test]
    public function it_returns_the_default_template_for_non_whitelisted_classes(): void
    {
        $resolver = $this->createResolver();

        $template = $resolver->resolveChromeTemplate(AttributeEditModalDataTable::class);

        $this->assertSame('@DataTables/modal/bootstrap5/edit_modal.html.twig', $template);
    }

    #[Test]
    public function it_resolves_the_body_template_from_bundle_config(): void
    {
        $resolver = $this->createResolver();

        $template = $resolver->resolveBodyTemplate();

        $this->assertSame('@DataTables/modal/_form_body_bs5.html.twig', $template);
    }

    #[Test]
    public function it_resolves_columns_from_a_registered_data_table(): void
    {
        $resolver = $this->createResolver([
            ColumnsEditModalDataTable::class => new ColumnsEditModalDataTable(),
        ]);

        $columns = $resolver->resolveColumns(ColumnsEditModalDataTable::class);

        $this->assertCount(1, $columns);
        $this->assertSame('name', $columns[0]->getName());
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
     * @param array<string, AbstractDataTable> $services
     */
    private function createResolver(array $services = []): EditModalTemplateResolver
    {
        return new EditModalTemplateResolver(
            new TestContainer($services),
            '@DataTables/modal/bootstrap5/edit_modal.html.twig',
            '@DataTables/modal/_form_body_bs5.html.twig',
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

/**
 * @implements ContainerInterface<object>
 */
final readonly class TestContainer implements ContainerInterface
{
    /**
     * @param array<string, object> $services
     */
    public function __construct(private array $services)
    {
    }

    public function get(string $id): object
    {
        if (!$this->has($id)) {
            throw new \RuntimeException(\sprintf('Unknown service "%s".', $id));
        }

        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return \array_key_exists($id, $this->services);
    }
}
