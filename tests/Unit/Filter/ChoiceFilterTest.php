<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Filter;

use Pentiminax\UX\DataTables\Filter\ChoiceFilter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum ChoiceFilterTranslatableRole: string implements TranslatableInterface
{
    case Admin = 'admin';
    case User  = 'user';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('role.'.$this->value, [], null, $locale);
    }
}

/**
 * @internal
 */
#[CoversClass(ChoiceFilter::class)]
final class ChoiceFilterTest extends TestCase
{
    use BuildsFilterQueryBuilder;

    #[Test]
    public function it_serializes_options_using_value_label_map(): void
    {
        $filter = ChoiceFilter::new('status')
            ->label('Statut')
            ->options(['Draft' => 'draft', 'Published' => 'published']);

        $this->assertSame([
            'name'     => 'status',
            'type'     => 'select',
            'label'    => 'Statut',
            'options'  => ['draft' => 'Draft', 'published' => 'Published'],
            'multiple' => false,
        ], $filter->jsonSerialize());
    }

    #[Test]
    public function it_falls_back_to_enum_labels_until_translation(): void
    {
        $filter = ChoiceFilter::new('role')->options(ChoiceFilterTranslatableRole::class);

        $this->assertSame(['admin' => 'Admin', 'user' => 'User'], $filter->jsonSerialize()['options']);

        $filter->translateLabels($this->createRoleTranslator());

        $this->assertSame(
            ['admin' => 'Administrateur', 'user' => 'Utilisateur'],
            $filter->jsonSerialize()['options'],
        );
    }

    #[Test]
    public function it_clears_translatable_cases_when_options_are_reassigned(): void
    {
        $filter = ChoiceFilter::new('role')->options(ChoiceFilterTranslatableRole::class);
        $filter->options(['Draft' => 'draft']);
        $filter->translateLabels($this->createRoleTranslator());

        $this->assertSame(['draft' => 'Draft'], $filter->jsonSerialize()['options']);
    }

    /**
     * @param list<string>         $expectedWhere
     * @param array<string, mixed> $expectedParams
     */
    #[Test]
    #[DataProvider('provideConditions')]
    public function it_applies_a_condition(ChoiceFilter $filter, mixed $value, array $expectedWhere, array $expectedParams): void
    {
        $this->assertFilterProduces($filter, $value, $expectedWhere, $expectedParams);
    }

    /**
     * @return iterable<string, array{ChoiceFilter, mixed, list<string>, array<string, mixed>}>
     */
    public static function provideConditions(): iterable
    {
        yield 'single value' => [
            ChoiceFilter::new('status'),
            'draft',
            ['e.status = :filter_status'],
            ['filter_status' => 'draft'],
        ];

        yield 'multiple values' => [
            ChoiceFilter::new('status')->multiple(),
            ['draft', 'published'],
            ['e.status IN (:filter_status_in)'],
            ['filter_status_in' => ['draft', 'published']],
        ];

        yield 'multiple without values' => [
            ChoiceFilter::new('status')->multiple(),
            [],
            [],
            [],
        ];
    }

    private function createRoleTranslator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnMap([
            ['role.admin', [], null, null, 'Administrateur'],
            ['role.user', [], null, null, 'Utilisateur'],
        ]);

        return $translator;
    }
}
