<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Enum;

use Pentiminax\UX\DataTables\Enum\ActionType;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class ActionTypeTest extends TestCase
{
    public function test_all_cases_exist(): void
    {
        $cases = ActionType::cases();

        $this->assertCount(3, $cases);
        $this->assertContains(ActionType::Delete, $cases);
        $this->assertContains(ActionType::Detail, $cases);
        $this->assertContains(ActionType::Edit, $cases);
    }

    public function test_case_values(): void
    {
        $this->assertSame('DELETE', ActionType::Delete->value);
        $this->assertSame('DETAIL', ActionType::Detail->value);
        $this->assertSame('EDIT', ActionType::Edit->value);
    }

    public function test_from_string(): void
    {
        $this->assertSame(ActionType::Edit, ActionType::from('EDIT'));
    }
}
