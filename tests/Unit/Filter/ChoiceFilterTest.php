<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Filter;

use Pentiminax\UX\DataTables\Filter\ChoiceFilter;
use PHPUnit\Framework\Attributes\CoversClass;
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
    public function it_uses_the_enum_label_as_fallback_before_translation(): void
    {
        $filter = ChoiceFilter::new('role')->options(ChoiceFilterTranslatableRole::class);

        $this->assertSame(
            ['admin' => 'Admin', 'user' => 'User'],
            $filter->jsonSerialize()['options'],
        );
    }

    #[Test]
    public function it_translates_translatable_enum_option_labels(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnMap([
            ['role.admin', [], null, null, 'Administrateur'],
            ['role.user', [], null, null, 'Utilisateur'],
        ]);

        $filter = ChoiceFilter::new('role')->options(ChoiceFilterTranslatableRole::class);
        $filter->translateLabels($translator);

        $this->assertSame(
            ['admin' => 'Administrateur', 'user' => 'Utilisateur'],
            $filter->jsonSerialize()['options'],
        );
    }

    #[Test]
    public function it_clears_translatable_cases_when_options_are_reassigned(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects($this->never())->method('trans');

        $filter = ChoiceFilter::new('role')->options(ChoiceFilterTranslatableRole::class);
        $filter->options(['Draft' => 'draft']);
        $filter->translateLabels($translator);

        $this->assertSame(['draft' => 'Draft'], $filter->jsonSerialize()['options']);
    }

    #[Test]
    public function it_applies_an_equality_condition(): void
    {
        $qb = $this->createScalarFieldQueryBuilder();

        ChoiceFilter::new('status')->apply($qb, 'draft', 'e');

        $this->assertSame(['e.status = :filter_status'], $this->capturedWhere);
        $this->assertSame(['filter_status' => 'draft'], $this->capturedParams);
    }

    #[Test]
    public function it_applies_an_in_condition_when_multiple(): void
    {
        $qb = $this->createScalarFieldQueryBuilder();

        ChoiceFilter::new('status')->multiple()->apply($qb, ['draft', 'published'], 'e');

        $this->assertSame(['e.status IN (:filter_status_in)'], $this->capturedWhere);
        $this->assertSame(['filter_status_in' => ['draft', 'published']], $this->capturedParams);
    }

    #[Test]
    public function it_is_a_no_op_when_multiple_receives_no_values(): void
    {
        $qb = $this->createScalarFieldQueryBuilder();

        ChoiceFilter::new('status')->multiple()->apply($qb, [], 'e');

        $this->assertSame([], $this->capturedWhere);
    }
}
