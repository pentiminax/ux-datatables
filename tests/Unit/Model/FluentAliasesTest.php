<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\ChoiceColumn;
use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\EmailColumn;
use Pentiminax\UX\DataTables\Column\ImageColumn;
use Pentiminax\UX\DataTables\Column\TemplateColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Column\UrlColumn;
use Pentiminax\UX\DataTables\Enum\ColumnType;
use Pentiminax\UX\DataTables\Enum\Language;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Model\BulkAction;
use Pentiminax\UX\DataTables\Model\BulkActions;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\DataTableExtensions;
use Pentiminax\UX\DataTables\Model\DataTableOptions;
use Pentiminax\UX\DataTables\Tests\Support\CollectsDeprecations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class FluentAliasesTest extends TestCase
{
    use CollectsDeprecations;

    /**
     * @param \Closure(): object $factory
     * @param list<mixed>        $arguments
     */
    #[Test]
    #[DataProvider('provideAliases')]
    public function the_bare_method_matches_the_setter_that_deprecates_in_favor_of_it(\Closure $factory, string $setter, string $bare, array $arguments): void
    {
        $viaBare   = $factory();
        $viaSetter = $factory();

        $viaBare->{$bare}(...$arguments);
        $deprecations = $this->deprecationsOf(static fn () => $viaSetter->{$setter}(...$arguments));

        $this->assertEquals($viaBare, $viaSetter);
        $this->assertCount(1, $deprecations);
        $this->assertStringContainsString(\sprintf('"%s()" instead', $bare), $deprecations[0]);
    }

    /**
     * @return iterable<string, array{\Closure(): object, string, string, list<mixed>}>
     */
    public static function provideAliases(): iterable
    {
        $text = static fn (): TextColumn => TextColumn::new('name');

        $columnCases = [
            ['setClassName', 'className', ['cls']],
            ['setCellType', 'cellType', ['th']],
            ['setName', 'name', ['other']],
            ['setOrderable', 'orderable', [false]],
            ['setSearchable', 'searchable', [false]],
            ['setSearchNormalization', 'searchNormalization', [false]],
            ['setColumnControl', 'columnControl', [['colvisDropdown']]],
            ['setType', 'type', [ColumnType::NUM]],
            ['setWidth', 'width', ['10px']],
            ['setResponsivePriority', 'responsivePriority', [2]],
            ['setTitle', 'title', ['Title']],
            ['setVisible', 'visible', [false]],
            ['setData', 'data', ['a.b']],
            ['setDefaultContent', 'defaultContent', ['-']],
            ['setExportable', 'exportable', [false]],
            ['setField', 'field', ['a.b']],
            ['setOrderExpression', 'orderExpression', ['e.name']],
            ['setSearchField', 'searchField', ['a.b']],
            ['setCustomOption', 'customOption', ['foo', 'bar']],
            ['setPermission', 'permission', ['ROLE_ADMIN']],
        ];

        foreach ($columnCases as [$setter, $bare, $arguments]) {
            yield 'AbstractColumn::'.$setter => [$text, $setter, $bare, $arguments];
        }

        yield 'ImageColumn::setImageWidth' => [static fn () => ImageColumn::new('i'), 'setImageWidth', 'imageWidth', [10]];
        yield 'ImageColumn::setImageHeight' => [static fn () => ImageColumn::new('i'), 'setImageHeight', 'imageHeight', [10]];
        yield 'ImageColumn::setAlt' => [static fn () => ImageColumn::new('i'), 'setAlt', 'alt', ['alt']];
        yield 'ImageColumn::setPlaceholder' => [static fn () => ImageColumn::new('i'), 'setPlaceholder', 'placeholder', ['/p.png']];
        yield 'ChoiceColumn::setChoices' => [static fn () => ChoiceColumn::new('c'), 'setChoices', 'choices', [['a' => 'A']]];
        yield 'UrlColumn::setDisplayValue' => [static fn () => UrlColumn::new('u'), 'setDisplayValue', 'displayValue', ['Open']];
        yield 'UrlColumn::setDefaultProtocol' => [static fn () => UrlColumn::new('u'), 'setDefaultProtocol', 'defaultProtocol', ['https']];
        yield 'EmailColumn::setDisplayValue' => [static fn () => EmailColumn::new('e'), 'setDisplayValue', 'displayValue', ['Mail']];
        yield 'DateColumn::setFormat' => [static fn () => DateColumn::new('d'), 'setFormat', 'format', ['Y-m-d']];
        yield 'BooleanColumn::setToggleAjax' => [static fn () => BooleanColumn::new('b'), 'setToggleAjax', 'toggleAjax', ['id', 'PUT']];
        yield 'BooleanColumn::setEntityClass' => [static fn () => BooleanColumn::new('b'), 'setEntityClass', 'entityClass', [\stdClass::class]];
        yield 'TemplateColumn::setTemplate' => [static fn () => TemplateColumn::new('t'), 'setTemplate', 'template', ['a.html.twig', ['x' => 1]]];

        yield 'Actions::setColumnLabel' => [static fn () => new Actions(), 'setColumnLabel', 'columnLabel', ['Ops']];
        yield 'Actions::setColumnClassName' => [static fn () => new Actions(), 'setColumnClassName', 'columnClassName', ['cls']];
        yield 'Actions::setColumnControl' => [static fn () => new Actions(), 'setColumnControl', 'columnControl', [['x']]];
        yield 'BulkActions::setIdField' => [static fn () => new BulkActions(), 'setIdField', 'idField', ['uuid']];
        yield 'BulkAction::setClassName' => [static fn () => BulkAction::new('b'), 'setClassName', 'className', ['cls']];
        yield 'BulkAction::setPermission' => [static fn () => BulkAction::new('b'), 'setPermission', 'permission', ['ROLE_ADMIN']];
        yield 'Action::setClassName' => [static fn () => Action::edit(), 'setClassName', 'className', ['cls']];
        yield 'Action::setEntityClass' => [static fn () => Action::edit(), 'setEntityClass', 'entityClass', [\stdClass::class]];
        yield 'Action::setIdField' => [static fn () => Action::edit(), 'setIdField', 'idField', ['uuid']];
        yield 'Action::setPermission' => [static fn () => Action::edit(), 'setPermission', 'permission', ['ROLE_ADMIN']];
        yield 'Action::csrfTokenId' => [static fn () => Action::delete(), 'csrfTokenId', 'csrfToken', ['delete_book']];
        yield 'DataTableOptions::setLanguage' => [static fn () => new DataTableOptions(), 'setLanguage', 'language', [Language::FR]];
        yield 'DataTableOptions::setSearch' => [static fn () => new DataTableOptions(), 'setSearch', 'search', ['foo']];
        yield 'DataTable::setAttributes' => [static fn () => new DataTable('t'), 'setAttributes', 'attributes', [['class' => 'x']]];
        yield 'DataTable::setPermission' => [static fn () => new DataTable('t'), 'setPermission', 'permission', ['ROLE_ADMIN']];
    }

    #[Test]
    public function set_search_predicate_deprecates_in_favor_of_search_predicate(): void
    {
        $viaBare = TextColumn::new('name')->searchPredicate(static fn (): string => '1 = 1');
        $column  = TextColumn::new('name');

        $deprecations = $this->deprecationsOf(static fn () => $column->setSearchPredicate(static fn (): string => '1 = 1'));

        $this->assertTrue($viaBare->hasSearchPredicate());
        $this->assertTrue($column->hasSearchPredicate());
        $this->assertCount(1, $deprecations);
        $this->assertStringContainsString('"searchPredicate()"', $deprecations[0]);
    }

    #[Test]
    public function set_extensions_deprecates_without_a_replacement_alias(): void
    {
        $table = new DataTable('t');

        $deprecations = $this->deprecationsOf(static fn () => $table->setExtensions(new DataTableExtensions()));

        $this->assertCount(1, $deprecations);
        $this->assertStringContainsString('"extensions()"', $deprecations[0]);
    }
}
