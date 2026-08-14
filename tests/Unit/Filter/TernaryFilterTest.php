<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Filter;

use Pentiminax\UX\DataTables\Filter\TernaryFilter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
        $translator = $this->createStub(TranslatorInterface::class);
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

    /**
     * @param list<string>         $expectedWhere
     * @param array<string, mixed> $expectedParams
     */
    #[Test]
    #[DataProvider('provideStates')]
    public function it_applies_a_condition_per_state(TernaryFilter $filter, string $state, array $expectedWhere, array $expectedParams): void
    {
        $this->assertFilterProduces($filter, $state, $expectedWhere, $expectedParams);
    }

    /**
     * @return iterable<string, array{TernaryFilter, string, list<string>, array<string, mixed>}>
     */
    public static function provideStates(): iterable
    {
        yield 'nullable field, true state' => [
            TernaryFilter::new('verified')->field('emailVerifiedAt'),
            'true',
            ['e.emailVerifiedAt IS NOT NULL'],
            [],
        ];

        yield 'nullable field, false state' => [
            TernaryFilter::new('verified')->field('emailVerifiedAt'),
            'false',
            ['e.emailVerifiedAt IS  NULL'],
            [],
        ];

        yield 'explicit values' => [
            TernaryFilter::new('active')->values(true, false),
            'true',
            ['e.active = :filter_active_true'],
            ['filter_active_true' => true],
        ];

        yield 'unrecognized state' => [
            TernaryFilter::new('verified')->field('emailVerifiedAt'),
            'maybe',
            [],
            [],
        ];
    }
}
