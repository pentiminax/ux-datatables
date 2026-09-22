<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\DependencyInjection\Compiler;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Attribute\DataTableColumn;
use Pentiminax\UX\DataTables\Attribute\DataTableFilter;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\DependencyInjection\Compiler\DataTableRegistryPass;
use Pentiminax\UX\DataTables\DependencyInjection\Compiler\ValidateDataTableAttributesPass;
use Pentiminax\UX\DataTables\Filter\CheckboxFilter;
use Pentiminax\UX\DataTables\Filter\TextFilter;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\Filters;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

/**
 * @internal
 */
#[CoversClass(ValidateDataTableAttributesPass::class)]
final class ValidateDataTableAttributesPassTest extends TestCase
{
    #[Test]
    public function it_accepts_a_table_whose_declarations_all_resolve(): void
    {
        $this->process(ValidTableFixture::class);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function it_rejects_an_option_no_column_answers(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Option "wobble" is not supported by');

        $this->process(UnknownOptionTableFixture::class);
    }

    #[Test]
    public function it_names_the_table_that_reads_the_faulty_class(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('read by the table "%s"', UnknownOptionTableFixture::class));

        $this->process(UnknownOptionTableFixture::class);
    }

    #[Test]
    public function it_rejects_two_columns_claiming_the_same_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Two columns are declared under the name "label"');

        $this->process(DuplicateNameTableFixture::class);
    }

    #[Test]
    public function it_rejects_a_class_level_column_without_a_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must carry a name');

        $this->process(UnnamedClassColumnTableFixture::class);
    }

    #[Test]
    public function it_ignores_a_table_carrying_no_attribute(): void
    {
        $this->process(BareTableFixture::class);

        $this->expectNotToPerformAssertions();
    }

    /**
     * Both fixtures live in this file, so the assertion is on the file the container has to watch,
     * which is what an object resource actually tracks.
     */
    #[Test]
    public function it_tracks_the_data_class_as_a_container_resource(): void
    {
        $container = $this->process(ValidTableFixture::class);

        $tracked = array_map(static fn (object $resource) => (string) $resource, $container->getResources());

        $this->assertContains((new \ReflectionClass(ValidTableFixture::class))->getFileName(), $tracked);
        $this->assertContains((new \ReflectionClass(ValidDataFixture::class))->getFileName(), $tracked);
    }

    /**
     * The table class wins the resolution chain, so nothing ever reads the data class declarations.
     * Failing the build over them would reject an application that runs.
     */
    #[Test]
    public function it_leaves_the_data_class_alone_when_the_table_class_declares_columns(): void
    {
        $container = $this->process(ShadowingTableFixture::class);

        $tracked = array_map(static fn (object $resource) => (string) $resource, $container->getResources());

        $this->assertContains((new \ReflectionClass(DuplicateNameDataFixture::class))->getFileName(), $tracked);
    }

    #[Test]
    public function it_rejects_an_option_no_filter_answers(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Option "wobble" is not supported by');

        $this->process(UnknownFilterOptionTableFixture::class);
    }

    #[Test]
    public function it_rejects_a_filter_that_needs_a_closure(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('has no default condition');

        $this->process(CheckboxFilterTableFixture::class);
    }

    #[Test]
    public function it_leaves_the_data_class_filters_alone_when_the_table_class_declares_some(): void
    {
        $this->process(ShadowingFilterTableFixture::class);

        $this->expectNotToPerformAssertions();
    }

    /**
     * A table that builds its own filters reads no filter attribute, so the faulty declaration on
     * its data class is dead code. Failing the build over it would break a cache:clear for something
     * no request can reach.
     */
    #[Test]
    public function it_skips_the_filter_attributes_a_configure_filters_override_shadows(): void
    {
        $this->process(OwnFiltersTableFixture::class);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function it_skips_the_column_attributes_a_configure_columns_override_shadows(): void
    {
        $this->process(OwnColumnsTableFixture::class);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function it_rejects_a_data_class_field_the_entity_does_not_declare(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('the column "fullName" is ordered or searched on the field "fullName", which "Pentiminax\\UX\\DataTables\\Tests\\Unit\\DependencyInjection\\Compiler\\ProjectedEntityFixture" does not declare. Name it after the entity field');

        $this->process(UnmappedProjectionTableFixture::class);
    }

    #[Test]
    public function it_rejects_a_data_class_filter_the_entity_does_not_declare(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('the filter "fullName" is ordered or searched');

        $this->process(UnmappedFilterProjectionTableFixture::class);
    }

    #[Test]
    public function it_accepts_the_projected_fields_the_entity_backs(): void
    {
        $this->process(MappedProjectionTableFixture::class);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function it_leaves_a_column_building_its_own_search_predicate_alone(): void
    {
        $container = $this->process(CustomPredicateProjectionTableFixture::class);

        $log = implode("\n", $container->getCompiler()->getLog());

        self::assertStringContainsString('column "fullName"', $log);
        self::assertStringContainsString('builds its own search condition', $log);
    }

    #[Test]
    public function it_leaves_the_fields_alone_when_the_data_class_is_the_entity_class(): void
    {
        $this->process(SelfProjectedTableFixture::class);

        $this->expectNotToPerformAssertions();
    }

    /**
     * @param class-string $dataTableClass
     */
    private function process(string $dataTableClass): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition(
            'app.table',
            (new Definition($dataTableClass))->addTag(DataTableRegistryPass::TAG),
        );

        (new ValidateDataTableAttributesPass())->process($container);

        return $container;
    }
}

final class ValidDataFixture
{
    #[DataTableColumn(options: ['title' => 'Name'])]
    public string $name = '';
}

final class UnknownOptionDataFixture
{
    #[DataTableColumn(options: ['wobble' => true])]
    public string $name = '';
}

final class DuplicateNameDataFixture
{
    #[DataTableColumn(name: 'label')]
    public string $first = '';

    #[DataTableColumn(name: 'label')]
    public string $second = '';
}

#[AsDataTable(dataClass: ValidDataFixture::class)]
final class ValidTableFixture extends AbstractDataTable
{
}

#[AsDataTable(dataClass: UnknownOptionDataFixture::class)]
final class UnknownOptionTableFixture extends AbstractDataTable
{
}

#[AsDataTable(dataClass: DuplicateNameDataFixture::class)]
final class DuplicateNameTableFixture extends AbstractDataTable
{
}

#[AsDataTable(dataClass: ValidDataFixture::class)]
#[DataTableColumn(options: ['title' => 'Nameless'])]
final class UnnamedClassColumnTableFixture extends AbstractDataTable
{
}

final class BareTableFixture extends AbstractDataTable
{
}

#[AsDataTable(dataClass: DuplicateNameDataFixture::class)]
#[DataTableColumn(name: 'actions', options: ['orderable' => false])]
final class ShadowingTableFixture extends AbstractDataTable
{
}

final class UnknownFilterOptionDataFixture
{
    #[DataTableFilter(options: ['wobble' => true])]
    public string $name = '';
}

final class CheckboxFilterDataFixture
{
    #[DataTableFilter(type: CheckboxFilter::class)]
    public bool $flagged = true;
}

#[AsDataTable(dataClass: UnknownFilterOptionDataFixture::class)]
final class UnknownFilterOptionTableFixture extends AbstractDataTable
{
}

#[AsDataTable(dataClass: CheckboxFilterDataFixture::class)]
final class CheckboxFilterTableFixture extends AbstractDataTable
{
}

#[AsDataTable(dataClass: CheckboxFilterDataFixture::class)]
#[DataTableFilter(name: 'archived')]
final class ShadowingFilterTableFixture extends AbstractDataTable
{
}

#[AsDataTable(dataClass: CheckboxFilterDataFixture::class)]
final class OwnFiltersTableFixture extends AbstractDataTable
{
    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add(TextFilter::new('manual'));
    }
}

#[AsDataTable(dataClass: DuplicateNameDataFixture::class)]
final class OwnColumnsTableFixture extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        return [TextColumn::new('manual')];
    }
}

class ProjectedEntityFixture
{
    public string $name = '';

    public string $role = '';
}

final class UnmappedProjectionDataFixture
{
    #[DataTableColumn]
    public string $fullName = '';
}

final class UnmappedFilterProjectionDataFixture
{
    #[DataTableFilter]
    public string $fullName = '';
}

final class MappedProjectionDataFixture
{
    #[DataTableColumn]
    public string $name = '';

    #[DataTableColumn(options: ['field' => 'role'])]
    public string $roleLabel = '';

    #[DataTableColumn(options: ['field' => 'team.name'])]
    public string $teamName = '';

    #[DataTableColumn(options: ['orderable' => false, 'searchable' => false, 'globalSearchable' => false])]
    public string $computed = '';

    #[DataTableColumn(options: ['orderExpression' => 'HIDDEN_score', 'searchable' => false, 'globalSearchable' => false])]
    public string $score = '';

    #[DataTableFilter(options: ['field' => 'role'])]
    public string $roleFilter = '';
}

#[AsDataTable(dataClass: UnmappedProjectionDataFixture::class, entityClass: ProjectedEntityFixture::class)]
final class UnmappedProjectionTableFixture extends AbstractDataTable
{
}

#[AsDataTable(dataClass: UnmappedFilterProjectionDataFixture::class, entityClass: ProjectedEntityFixture::class)]
final class UnmappedFilterProjectionTableFixture extends AbstractDataTable
{
}

#[AsDataTable(dataClass: MappedProjectionDataFixture::class, entityClass: ProjectedEntityFixture::class)]
final class MappedProjectionTableFixture extends AbstractDataTable
{
}

#[AsDataTable(dataClass: UnmappedProjectionDataFixture::class)]
final class SelfProjectedTableFixture extends AbstractDataTable
{
}

final class SelfSearchingColumn extends TextColumn
{
    public function buildSearchPredicate(QueryBuilder $qb, string $alias, string $value, string $paramName): ?string
    {
        $qb->setParameter($paramName, '%'.$value.'%');

        return \sprintf('%s.name LIKE :%s', $alias, $paramName);
    }
}

final class CustomPredicateProjectionDataFixture
{
    #[DataTableColumn(type: SelfSearchingColumn::class, options: ['orderable' => false])]
    public string $fullName = '';
}

#[AsDataTable(dataClass: CustomPredicateProjectionDataFixture::class, entityClass: ProjectedEntityFixture::class)]
final class CustomPredicateProjectionTableFixture extends AbstractDataTable
{
}
