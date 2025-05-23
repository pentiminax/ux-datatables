<?php

namespace Pentiminax\UX\DataTables\Tests\Model\Extensions;

use Pentiminax\UX\DataTables\Enum\ButtonType;
use Pentiminax\UX\DataTables\Model\Extensions\ButtonsExtension;
use PHPUnit\Framework\TestCase;

class ButtonsExtensionTest extends TestCase
{
    public function testButtonsExtensionToArray(): void
    {
        $buttons = [];
        foreach (ButtonType::cases() as $buttonType) {
            $buttons[] = $buttonType->value;
        }

        $extension = new ButtonsExtension($buttons);

        $expectedArray = [
            ButtonType::COPY->value,
            ButtonType::CSV->value,
            ButtonType::EXCEL->value,
            ButtonType::PDF->value,
            ButtonType::PRINT->value
        ];

        $this->assertEquals($expectedArray, $extension->jsonSerialize());
    }
}