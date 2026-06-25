<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Filter;

use Pentiminax\UX\DataTables\Filter\TernaryFilter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[CoversClass(TernaryFilter::class)]
final class TernaryFilterTest extends TestCase
{
    use BuildsFilterQueryBuilder;

    #[Test]
    public function it_serializes_state_labels(): void
    {
        $filter = TernaryFilter::new('verified')
            ->field('emailVerifiedAt')
            ->trueLabel('Verified')
            ->falseLabel('Not verified');

        $this->assertSame([
            'name'       => 'verified',
            'type'       => 'ternary',
            'label'      => 'Verified',
            'trueLabel'  => 'Verified',
            'falseLabel' => 'Not verified',
        ], $filter->jsonSerialize());
    }

    #[Test]
    public function it_translates_state_labels_falling_back_to_defaults(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->method('trans')
            ->willReturnMap([
                ['Yes', [], null, null, 'Oui'],
                ['filter.verified.no', [], null, null, 'Non vérifié'],
            ]);

        $filter = TernaryFilter::new('verified')
            ->field('emailVerifiedAt')
            ->falseLabel('filter.verified.no');

        $filter->translateLabels($translator);

        $serialized = $filter->jsonSerialize();
        $this->assertSame('Oui', $serialized['trueLabel']);
        $this->assertSame('Non vérifié', $serialized['falseLabel']);
    }

    #[Test]
    public function it_applies_is_not_null_for_true_state(): void
    {
        $qb = $this->createScalarFieldQueryBuilder();

        TernaryFilter::new('verified')->field('emailVerifiedAt')->apply($qb, 'true', 'e');

        $this->assertSame(['e.emailVerifiedAt IS NOT NULL'], $this->capturedWhere);
    }

    #[Test]
    public function it_applies_is_null_for_false_state(): void
    {
        $qb = $this->createScalarFieldQueryBuilder();

        TernaryFilter::new('verified')->field('emailVerifiedAt')->apply($qb, 'false', 'e');

        $this->assertSame(['e.emailVerifiedAt IS  NULL'], $this->capturedWhere);
    }

    #[Test]
    public function it_compares_against_values_when_provided(): void
    {
        $qb = $this->createScalarFieldQueryBuilder();

        TernaryFilter::new('active')->values(true, false)->apply($qb, 'true', 'e');

        $this->assertSame(['e.active = :filter_active_true'], $this->capturedWhere);
        $this->assertSame(['filter_active_true' => true], $this->capturedParams);
    }

    #[Test]
    public function it_is_a_no_op_for_unrecognized_state(): void
    {
        $qb = $this->createScalarFieldQueryBuilder();

        TernaryFilter::new('verified')->field('emailVerifiedAt')->apply($qb, 'maybe', 'e');

        $this->assertSame([], $this->capturedWhere);
    }
}
