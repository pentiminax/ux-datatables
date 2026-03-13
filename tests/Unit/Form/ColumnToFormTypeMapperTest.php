<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Form;

use Pentiminax\UX\DataTables\Form\ColumnToFormTypeMapper;
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
        $result = $this->mapper->map([
            'name'          => 'active',
            'title'         => 'Active',
            'customOptions' => ['renderAsSwitch' => true],
        ]);

        $this->assertNotNull($result);
        $this->assertSame(CheckboxType::class, $result['formType']);
        $this->assertSame('Active', $result['options']['label']);
        $this->assertFalse($result['options']['required']);
    }

    public function test_choices_maps_to_choice_type(): void
    {
        $choices = ['draft' => 'Draft', 'published' => 'Published'];

        $result = $this->mapper->map([
            'name'          => 'status',
            'title'         => 'Status',
            'customOptions' => ['choices' => $choices],
        ]);

        $this->assertNotNull($result);
        $this->assertSame(ChoiceType::class, $result['formType']);
        $this->assertSame(array_flip($choices), $result['options']['choices']);
        $this->assertFalse($result['options']['required']);
    }

    public function test_date_format_maps_to_date_type(): void
    {
        $result = $this->mapper->map([
            'name'          => 'createdAt',
            'title'         => 'Created At',
            'customOptions' => ['dateFormat' => 'Y-m-d'],
        ]);

        $this->assertNotNull($result);
        $this->assertSame(DateType::class, $result['formType']);
        $this->assertSame('single_text', $result['options']['widget']);
    }

    #[DataProvider('numericTypeProvider')]
    public function test_numeric_types_map_to_number_type(string $type): void
    {
        $result = $this->mapper->map([
            'name'  => 'price',
            'title' => 'Price',
            'type'  => $type,
        ]);

        $this->assertNotNull($result);
        $this->assertSame(NumberType::class, $result['formType']);
    }

    public static function numericTypeProvider(): \Generator
    {
        yield 'num' => ['num'];
        yield 'num-fmt' => ['num-fmt'];
        yield 'html-num' => ['html-num'];
        yield 'html-num-fmt' => ['html-num-fmt'];
    }

    #[DataProvider('stringTypeProvider')]
    public function test_string_types_map_to_text_type(string $type): void
    {
        $result = $this->mapper->map([
            'name'  => 'name',
            'title' => 'Name',
            'type'  => $type,
        ]);

        $this->assertNotNull($result);
        $this->assertSame(TextType::class, $result['formType']);
    }

    public static function stringTypeProvider(): \Generator
    {
        yield 'string' => ['string'];
        yield 'string-utf8' => ['string-utf8'];
    }

    public function test_html_type_maps_to_textarea(): void
    {
        $result = $this->mapper->map([
            'name'  => 'description',
            'title' => 'Description',
            'type'  => 'html',
        ]);

        $this->assertNotNull($result);
        $this->assertSame(TextareaType::class, $result['formType']);
    }

    public function test_action_column_is_skipped(): void
    {
        $result = $this->mapper->map([
            'name'    => 'actions',
            'title'   => 'Actions',
            'actions' => [['type' => 'DELETE']],
        ]);

        $this->assertNull($result);
    }

    public function test_template_column_is_skipped(): void
    {
        $result = $this->mapper->map([
            'name'          => 'custom',
            'title'         => 'Custom',
            'customOptions' => ['templatePath' => 'some/template.html.twig'],
        ]);

        $this->assertNull($result);
    }

    public function test_url_column_with_route_name_is_skipped(): void
    {
        $result = $this->mapper->map([
            'name'          => 'link',
            'title'         => 'Link',
            'customOptions' => ['routeName' => 'app_show'],
        ]);

        $this->assertNull($result);
    }

    public function test_url_column_with_template_is_skipped(): void
    {
        $result = $this->mapper->map([
            'name'          => 'link',
            'title'         => 'Link',
            'customOptions' => ['template' => '/items/{id}'],
        ]);

        $this->assertNull($result);
    }

    public function test_hide_when_updating_is_skipped(): void
    {
        $result = $this->mapper->map([
            'name'          => 'createdAt',
            'title'         => 'Created At',
            'type'          => 'string',
            'customOptions' => ['hideWhenUpdating' => true],
        ]);

        $this->assertNull($result);
    }

    public function test_unknown_type_defaults_to_text_type(): void
    {
        $result = $this->mapper->map([
            'name'  => 'unknown',
            'title' => 'Unknown',
        ]);

        $this->assertNotNull($result);
        $this->assertSame(TextType::class, $result['formType']);
    }

    public function test_label_is_set_from_title(): void
    {
        $result = $this->mapper->map([
            'name'  => 'name',
            'title' => 'Full Name',
            'type'  => 'string',
        ]);

        $this->assertNotNull($result);
        $this->assertSame('Full Name', $result['options']['label']);
    }
}
