<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Form;

use Pentiminax\UX\DataTables\Column\ActionColumn;
use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TemplateColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Column\UrlColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
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

    /**
     * @param array{formType: class-string, options: array<string, mixed>} $expected
     */
    #[DataProvider('mappedColumnProvider')]
    public function test_column_maps_to_a_form_type_with_its_options(array $expected, ColumnInterface $column): void
    {
        $this->assertSame($expected, $this->mapper->map($column));
    }

    public static function mappedColumnProvider(): \Generator
    {
        yield 'boolean switch' => [
            ['formType' => CheckboxType::class, 'options' => ['label' => 'Active', 'required' => false]],
            TextColumn::new('active', 'Active')->setCustomOption('renderAsSwitch', true),
        ];

        yield 'choices' => [
            ['formType' => ChoiceType::class, 'options' => [
                'label'    => 'Status',
                'choices'  => ['Draft' => 'draft', 'Published' => 'published'],
                'required' => false,
            ]],
            TextColumn::new('status', 'Status')->setCustomOption('choices', ['draft' => 'Draft', 'published' => 'Published']),
        ];

        yield 'date' => [
            ['formType' => DateType::class, 'options' => ['label' => 'Created At', 'widget' => 'single_text']],
            DateColumn::new('createdAt', 'Created At'),
        ];

        $number = ['formType' => NumberType::class, 'options' => ['label' => 'Price', 'html5' => true]];

        yield 'num' => [$number, NumberColumn::new('price', 'Price')];
        yield 'num-fmt' => [$number, NumberColumn::new('price', 'Price')->formatted()];
        yield 'html-num' => [$number, NumberColumn::new('price', 'Price')->html()];
        yield 'html-num-fmt' => [$number, NumberColumn::new('price', 'Price')->html()->formatted()];

        $text = ['formType' => TextType::class, 'options' => ['label' => 'Full Name']];

        yield 'string' => [$text, TextColumn::new('name', 'Full Name')];
        yield 'string-utf8' => [$text, TextColumn::new('name', 'Full Name')->utf8()];

        yield 'html' => [
            ['formType' => TextareaType::class, 'options' => ['label' => 'Description']],
            TextColumn::new('description', 'Description')->html(),
        ];
    }

    #[DataProvider('skippedColumnProvider')]
    public function test_column_is_skipped(ColumnInterface $column): void
    {
        $this->assertNull($this->mapper->map($column));
    }

    public static function skippedColumnProvider(): \Generator
    {
        yield 'action column' => [ActionColumn::fromActions('actions', 'Actions', new Actions([]))];
        yield 'template column' => [TemplateColumn::new('custom', 'Custom')->setCustomOption('templatePath', 'some/template.html.twig')];
        yield 'url column' => [UrlColumn::new('link', 'Link')];
        yield 'nested field path' => [TextColumn::new('author', 'Author')->setField('author.firstName')];
        yield 'hidden when updating' => [TextColumn::new('createdAt', 'Created At')->setCustomOption('hideWhenUpdating', true)];
    }
}
