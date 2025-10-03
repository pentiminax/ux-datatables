<?php

namespace Pentiminax\UX\DataTables\Tests\Model\Options;

use Pentiminax\UX\DataTables\Model\Options\AjaxOption;
use PHPUnit\Framework\TestCase;

class AjaxOptionTest extends TestCase
{
    public function testAjaxOption(): void
    {
        $ajaxOption = new AjaxOption(
            url: '/api/data',
            dataSrc: 'data',
            type: 'POST',
        );

        $this->assertEquals('/api/data', $ajaxOption->getUrl());
        $this->assertEquals('data', $ajaxOption->getDataSrc());
        $this->assertEquals('POST', $ajaxOption->getType());
    }
}