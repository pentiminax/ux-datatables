<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Column\ChoiceColumn;
use PHPUnit\Framework\TestCase;

enum TestStatus: string
{
    case Active   = 'active';
    case Inactive = 'inactive';
    case Pending  = 'pending';
}

class ChoiceColumnTest extends TestCase
{
    public function testDefaultChoiceColumnHasNoChoicesOrBadges(): void
    {
        $data = ChoiceColumn::new('status')->jsonSerialize();

        $this->assertArrayNotHasKey('choiceChoices', $data);
        $this->assertArrayNotHasKey('choiceBadges', $data);
        $this->assertArrayNotHasKey('choiceDefaultBadge', $data);
    }

    public function testColumnTypeIsHtml(): void
    {
        $data = ChoiceColumn::new('status')->jsonSerialize();

        $this->assertSame('html', $data['type']);
    }

    public function testSetChoicesWithArray(): void
    {
        $data = ChoiceColumn::new('status')
            ->setChoices(['active' => 'Active', 'inactive' => 'Inactive'])
            ->jsonSerialize();

        $this->assertArrayHasKey('choiceChoices', $data);
        $this->assertSame(['active' => 'Active', 'inactive' => 'Inactive'], $data['choiceChoices']);
    }

    public function testSetChoicesWithBackedEnumClass(): void
    {
        $data = ChoiceColumn::new('status')
            ->setChoices(TestStatus::class)
            ->jsonSerialize();

        $this->assertArrayHasKey('choiceChoices', $data);
        $this->assertSame([
            'active'   => 'Active',
            'inactive' => 'Inactive',
            'pending'  => 'Pending',
        ], $data['choiceChoices']);
    }

    public function testSetChoicesWithInvalidClassThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ChoiceColumn::new('status')->setChoices(\stdClass::class);
    }

    public function testRenderAsBadgeSetsOptions(): void
    {
        $data = ChoiceColumn::new('status')
            ->setChoices(['active' => 'Active', 'inactive' => 'Inactive'])
            ->renderAsBadges(['active' => 'success', 'inactive' => 'danger'], 'secondary')
            ->jsonSerialize();

        $this->assertArrayHasKey('choiceBadges', $data);
        $this->assertArrayHasKey('choiceDefaultBadge', $data);
        $this->assertSame(['active' => 'success', 'inactive' => 'danger'], $data['choiceBadges']);
        $this->assertSame('secondary', $data['choiceDefaultBadge']);
    }

    public function testRenderAsBadgeDefaultVariantFallback(): void
    {
        $data = ChoiceColumn::new('status')
            ->setChoices(['active' => 'Active'])
            ->renderAsBadges()
            ->jsonSerialize();

        $this->assertSame('secondary', $data['choiceDefaultBadge']);
    }

    public function testDefaultTitleFallsBackToName(): void
    {
        $data = ChoiceColumn::new('status')->jsonSerialize();

        $this->assertSame('status', $data['title']);
    }

    public function testExplicitTitleIsUsed(): void
    {
        $data = ChoiceColumn::new('status', 'Status')->jsonSerialize();

        $this->assertSame('Status', $data['title']);
    }
}
