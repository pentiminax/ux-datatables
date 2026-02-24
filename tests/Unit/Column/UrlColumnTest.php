<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Column\UrlColumn;
use PHPUnit\Framework\TestCase;

class UrlColumnTest extends TestCase
{
    public function testFactoryCreatesHtmlTypeColumn(): void
    {
        $data = UrlColumn::new('website', 'Website')->jsonSerialize();

        $this->assertSame('html', $data['type']);
        $this->assertSame('website', $data['data']);
        $this->assertSame('website', $data['name']);
        $this->assertSame('Website', $data['title']);
    }

    public function testDefaultTitleFallsBackToName(): void
    {
        $data = UrlColumn::new('website')->jsonSerialize();

        $this->assertSame('website', $data['title']);
    }

    public function testOpenInNewTabSetsTargetBlank(): void
    {
        $data = UrlColumn::new('website')
            ->openInNewTab()
            ->jsonSerialize();

        $this->assertSame('_blank', $data['urlTarget']);
    }

    public function testSetTargetStoresCustomTarget(): void
    {
        $data = UrlColumn::new('website')
            ->setTarget('_self')
            ->jsonSerialize();

        $this->assertSame('_self', $data['urlTarget']);
    }

    public function testSetDisplayValueStoresText(): void
    {
        $data = UrlColumn::new('website')
            ->setDisplayValue('Visit')
            ->jsonSerialize();

        $this->assertSame('Visit', $data['urlDisplayValue']);
    }

    public function testRouteStoresNameAndParams(): void
    {
        $data = UrlColumn::new('website')
            ->route('app_user_show', ['id' => 'id'])
            ->jsonSerialize();

        $this->assertArrayNotHasKey('urlRouteName', $data);
        $this->assertSame(['id' => 'id'], $data['urlRouteParams']);
    }

    public function testShowExternalIconStoresFlag(): void
    {
        $data = UrlColumn::new('website')
            ->showExternalIcon()
            ->jsonSerialize();

        $this->assertTrue($data['urlShowExternalIcon']);
    }

    public function testShowExternalIconCanBeDisabled(): void
    {
        $data = UrlColumn::new('website')
            ->showExternalIcon(false)
            ->jsonSerialize();

        $this->assertFalse($data['urlShowExternalIcon']);
    }

    public function testSetUrlTemplateStoresTemplate(): void
    {
        $data = UrlColumn::new('website')
            ->setUrlTemplate('/users/{id}')
            ->jsonSerialize();

        $this->assertSame('/users/{id}', $data['urlTemplate']);
    }

    public function testDefaultSerializationHasNoUrlOptions(): void
    {
        $data = UrlColumn::new('website')->jsonSerialize();

        $this->assertArrayNotHasKey('urlTarget', $data);
        $this->assertArrayNotHasKey('urlDisplayValue', $data);
        $this->assertArrayNotHasKey('urlRouteName', $data);
        $this->assertArrayNotHasKey('urlRouteParams', $data);
        $this->assertArrayNotHasKey('urlTemplate', $data);
        $this->assertArrayNotHasKey('urlShowExternalIcon', $data);
    }

    public function testFullConfigurationSerialization(): void
    {
        $data = UrlColumn::new('website', 'User Link')
            ->route('app_user_show', ['id' => 'id'])
            ->setUrlTemplate('/users/{id}')
            ->openInNewTab()
            ->setDisplayValue('View')
            ->showExternalIcon()
            ->jsonSerialize();

        $this->assertSame('html', $data['type']);
        $this->assertSame('_blank', $data['urlTarget']);
        $this->assertSame('View', $data['urlDisplayValue']);
        $this->assertSame(['id' => 'id'], $data['urlRouteParams']);
        $this->assertSame('/users/{id}', $data['urlTemplate']);
        $this->assertTrue($data['urlShowExternalIcon']);
    }

    public function testGetRouteNameReturnsStoredName(): void
    {
        $column = UrlColumn::new('website')
            ->route('app_user_show', ['id' => 'id']);

        $this->assertSame('app_user_show', $column->getRouteName());
    }

    public function testGetRouteNameReturnsNullByDefault(): void
    {
        $column = UrlColumn::new('website');

        $this->assertNull($column->getRouteName());
    }

    public function testGetRouteParamsReturnsStoredParams(): void
    {
        $column = UrlColumn::new('website')
            ->route('app_user_show', ['id' => 'id', 'slug' => 'slug']);

        $this->assertSame(['id' => 'id', 'slug' => 'slug'], $column->getRouteParams());
    }
}
