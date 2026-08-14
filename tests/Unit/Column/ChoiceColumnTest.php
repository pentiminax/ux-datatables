<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Column\ChoiceColumn;
use Pentiminax\UX\DataTables\Tests\Support\DataTableTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;

/**
 * @internal
 */
#[CoversClass(ChoiceColumn::class)]
final class ChoiceColumnTest extends DataTableTestCase
{
    #[Test]
    public function it_creates_html_type_column_without_choices_or_badges(): void
    {
        $column = ChoiceColumn::new('status', 'Status');

        $this->assertColumnHeader($column, 'html', 'status', 'Status');
        $this->assertCustomOptions([], $column);
    }

    #[Test]
    #[DataProvider('provideChoices')]
    public function it_normalizes_choices(mixed $choices, array $expected): void
    {
        $column = ChoiceColumn::new('status')->setChoices($choices);

        $this->assertCustomOption($expected, 'choices', $column);
    }

    /**
     * @return iterable<string, array{mixed, array<string, string>}>
     */
    public static function provideChoices(): iterable
    {
        yield 'label indexed array' => [
            ['Active' => 'active', 'Inactive' => 'inactive'],
            ['active' => 'Active', 'inactive' => 'Inactive'],
        ];

        yield 'non string values are cast to string keys' => [
            ['One' => 1, 'Two' => 2],
            ['1' => 'One', '2' => 'Two'],
        ];

        yield 'enum cases exposing getLabel()' => [
            TestStatusWithLabel::cases(),
            ['active' => 'Active ✅', 'inactive' => 'Inactive ❌'],
        ];

        yield 'backed enum class' => [
            TestStatus::class,
            ['active' => 'Active', 'inactive' => 'Inactive', 'pending' => 'Pending'],
        ];

        yield 'backed enum cases' => [
            TestStatus::cases(),
            ['active' => 'Active', 'inactive' => 'Inactive', 'pending' => 'Pending'],
        ];
    }

    #[Test]
    public function it_rejects_a_class_that_is_not_an_enum(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ChoiceColumn::new('status')->setChoices(\stdClass::class);
    }

    #[Test]
    public function it_sets_badge_options(): void
    {
        $column = ChoiceColumn::new('status')
            ->setChoices(['active' => 'Active', 'inactive' => 'Inactive'])
            ->renderAsBadges(['active' => 'success', 'inactive' => 'danger'], 'secondary');

        $this->assertCustomOption(['active' => 'success', 'inactive' => 'danger'], 'renderAsBadges', $column);
        $this->assertCustomOption('secondary', 'defaultBadgeVariant', $column);
    }

    #[Test]
    public function it_falls_back_to_secondary_as_default_badge_variant(): void
    {
        $column = ChoiceColumn::new('status')
            ->setChoices(['active' => 'Active'])
            ->renderAsBadges();

        $this->assertCustomOption([], 'renderAsBadges', $column);
        $this->assertCustomOption('secondary', 'defaultBadgeVariant', $column);
    }

    #[Test]
    #[TestWith([['active' => 'invalid']])]
    #[TestWith([[], 'invalid'])]
    public function it_rejects_an_invalid_badge_variant(array $mapped, string $default = 'secondary'): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ChoiceColumn::new('status')->renderAsBadges($mapped, $default);
    }
}
