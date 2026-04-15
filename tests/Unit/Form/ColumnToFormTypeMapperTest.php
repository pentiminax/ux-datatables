<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Form;

use Pentiminax\UX\DataTables\Column\ActionColumn;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TemplateColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Form\ColumnToFormTypeMapper;
use Pentiminax\UX\DataTables\Model\Actions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * @internal
 */
class ColumnToFormTypeMapperTest extends TestCase
{
    private ColumnToFormTypeMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ColumnToFormTypeMapper();
    }

    public function test_boolean_switch_maps_to_checkbox(): void
    {
        $column = TextColumn::new('active', 'Active')
            ->setCustomOption('renderAsSwitch', true);

        $result = $this->mapper->map($column);

        $this->assertNotNull($result);
        $this->assertSame(CheckboxType::class, $result['formType']);
        $this->assertSame('Active', $result['options']['label']);
        $this->assertFalse($result['options']['required']);
    }

    public function test_choices_maps_to_choice_type(): void
    {
        $choices = ['draft' => 'Draft', 'published' => 'Published'];

        $column = TextColumn::new('status', 'Status')
            ->setCustomOption('choices', $choices);

        $result = $this->mapper->map($column);

        $this->assertNotNull($result);
        $this->assertSame(ChoiceType::class, $result['formType']);
        $this->assertSame(array_flip($choices), $result['options']['choices']);
        $this->assertFalse($result['options']['required']);
    }

    public function test_date_format_maps_to_date_type(): void
    {
        $column = TextColumn::new('createdAt', 'Created At')
            ->setCustomOption('dateFormat', 'Y-m-d');

        $result = $this->mapper->map($column);

        $this->assertNotNull($result);
        $this->assertSame(DateType::class, $result['formType']);
        $this->assertSame('single_text', $result['options']['widget']);
    }

    #[DataProvider('numericColumnProvider')]
    public function test_numeric_types_map_to_number_type(string $factory): void
    {
        $column = NumberColumn::$factory('price', 'Price');

        $result = $this->mapper->map($column);

        $this->assertNotNull($result);
        $this->assertSame(NumberType::class, $result['formType']);
    }

    public static function numericColumnProvider(): \Generator
    {
        yield 'num' => ['new'];
        yield 'num-fmt' => ['formatted'];
        yield 'html-num' => ['html'];
        yield 'html-num-fmt' => ['htmlFormatted'];
    }

    #[DataProvider('stringColumnProvider')]
    public function test_string_types_map_to_text_type(string $factory): void
    {
        $column = TextColumn::$factory('name', 'Name');

        $result = $this->mapper->map($column);

        $this->assertNotNull($result);
        $this->assertSame(TextType::class, $result['formType']);
    }

    public static function stringColumnProvider(): \Generator
    {
        yield 'string' => ['new'];
        yield 'string-utf8' => ['utf8'];
    }

    public function test_html_type_maps_to_textarea(): void
    {
        $column = TextColumn::html('description', 'Description');

        $result = $this->mapper->map($column);

        $this->assertNotNull($result);
        $this->assertSame(TextareaType::class, $result['formType']);
    }

    public function test_action_column_is_skipped(): void
    {
        $column = ActionColumn::fromActions('actions', 'Actions', new Actions([]));

        $result = $this->mapper->map($column);

        $this->assertNull($result);
    }

    public function test_template_column_is_skipped(): void
    {
        $column = TemplateColumn::new('custom', 'Custom')
            ->setCustomOption('templatePath', 'some/template.html.twig');

        $result = $this->mapper->map($column);

        $this->assertNull($result);
    }

    public function test_url_column_with_route_name_is_skipped(): void
    {
        $column = TextColumn::new('link', 'Link')
            ->setCustomOption('routeName', 'app_show');

        $result = $this->mapper->map($column);

        $this->assertNull($result);
    }

    public function test_url_column_with_template_is_skipped(): void
    {
        $column = TextColumn::new('link', 'Link')
            ->setCustomOption('template', '/items/{id}');

        $result = $this->mapper->map($column);

        $this->assertNull($result);
    }

    public function test_nested_field_path_is_skipped(): void
    {
        $column = TextColumn::new('author', 'Author')
            ->setField('author.firstName');

        $result = $this->mapper->map($column);

        $this->assertNull($result);
    }

    public function test_hide_when_updating_is_skipped(): void
    {
        $column = TextColumn::new('createdAt', 'Created At')
            ->setCustomOption('hideWhenUpdating', true);

        $result = $this->mapper->map($column);

        $this->assertNull($result);
    }

    public function test_unknown_type_defaults_to_text_type(): void
    {
        $column = TextColumn::new('unknown', 'Unknown');

        $result = $this->mapper->map($column);

        $this->assertNotNull($result);
        $this->assertSame(TextType::class, $result['formType']);
    }

    public function test_label_is_set_from_title(): void
    {
        $column = TextColumn::new('name', 'Full Name');

        $result = $this->mapper->map($column);

        $this->assertNotNull($result);
        $this->assertSame('Full Name', $result['options']['label']);
    }
}
