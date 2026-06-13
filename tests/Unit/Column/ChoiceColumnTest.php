<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Column\ChoiceColumn;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ChoiceColumn::class)]
final class ChoiceColumnTest extends TestCase
{
    #[Test]
    public function it_has_no_choices_or_badges_by_default(): void
    {
        $data = ChoiceColumn::new('status')->jsonSerialize();

        $this->assertArrayNotHasKey('customOptions', $data);
    }

    #[Test]
    public function it_has_html_column_type(): void
    {
        $data = ChoiceColumn::new('status')->jsonSerialize();

        $this->assertSame('html', $data['type']);
    }

    #[Test]
    public function it_sets_choices_from_array_using_easyadmin_convention(): void
    {
        // EasyAdmin convention: keys are the human-readable labels, values are the stored values.
        $data = ChoiceColumn::new('status')
            ->setChoices(['Active' => 'active', 'Inactive' => 'inactive'])
            ->jsonSerialize();

        $this->assertArrayHasKey('choices', $data['customOptions']);
        // Stored internally as [value => label] so the renderer can resolve label from the cell value.
        $this->assertSame(['active' => 'Active', 'inactive' => 'Inactive'], $data['customOptions']['choices']);
    }

    #[Test]
    public function it_casts_non_string_choice_values_to_string_keys(): void
    {
        $data = ChoiceColumn::new('status')
            ->setChoices(['One' => 1, 'Two' => 2])
            ->jsonSerialize();

        $this->assertSame(['1' => 'One', '2' => 'Two'], $data['customOptions']['choices']);
    }

    #[Test]
    public function it_resolves_enum_labels_from_get_label_method(): void
    {
        $data = ChoiceColumn::new('status')
            ->setChoices(TestStatusWithLabel::cases())
            ->jsonSerialize();

        $this->assertSame([
            'active'   => 'Active ✅',
            'inactive' => 'Inactive ❌',
        ], $data['customOptions']['choices']);
    }

    #[Test]
    #[DataProvider('provideBackedEnumChoices')]
    public function it_sets_choices_from_backed_enum(mixed $input): void
    {
        $data = ChoiceColumn::new('status')
            ->setChoices($input)
            ->jsonSerialize();

        $this->assertArrayHasKey('choices', $data['customOptions']);
        $this->assertSame([
            'active'   => 'Active',
            'inactive' => 'Inactive',
            'pending'  => 'Pending',
        ], $data['customOptions']['choices']);
    }

    public static function provideBackedEnumChoices(): iterable
    {
        yield 'enum class' => [TestStatus::class];
        yield 'enum cases' => [TestStatus::cases()];
    }

    #[Test]
    public function it_throws_exception_for_invalid_choices_class(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ChoiceColumn::new('status')->setChoices(\stdClass::class);
    }

    #[Test]
    public function it_sets_badge_options(): void
    {
        $data = ChoiceColumn::new('status')
            ->setChoices(['active' => 'Active', 'inactive' => 'Inactive'])
            ->renderAsBadges(['active' => 'success', 'inactive' => 'danger'], 'secondary')
            ->jsonSerialize();

        $this->assertArrayHasKey('renderAsBadges', $data['customOptions']);
        $this->assertArrayHasKey('defaultBadgeVariant', $data['customOptions']);
        $this->assertSame(['active' => 'success', 'inactive' => 'danger'], $data['customOptions']['renderAsBadges']);
        $this->assertSame('secondary', $data['customOptions']['defaultBadgeVariant']);
    }

    #[Test]
    public function it_falls_back_to_secondary_as_default_badge_variant(): void
    {
        $data = ChoiceColumn::new('status')
            ->setChoices(['active' => 'Active'])
            ->renderAsBadges()
            ->jsonSerialize();

        $this->assertSame([], $data['customOptions']['renderAsBadges']);
        $this->assertSame('secondary', $data['customOptions']['defaultBadgeVariant']);
    }

    #[Test]
    #[TestWith([['active' => 'invalid']])]
    #[TestWith([[], 'invalid'])]
    public function it_throws_exception_for_invalid_badge_variant(array $mapped, string $default = 'secondary'): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ChoiceColumn::new('status')->renderAsBadges($mapped, $default);
    }

    #[Test]
    public function it_falls_back_to_name_as_default_title(): void
    {
        $data = ChoiceColumn::new('status')->jsonSerialize();

        $this->assertSame('status', $data['title']);
    }

    #[Test]
    public function it_uses_explicit_title(): void
    {
        $data = ChoiceColumn::new('status', 'Status')->jsonSerialize();

        $this->assertSame('Status', $data['title']);
    }
}
