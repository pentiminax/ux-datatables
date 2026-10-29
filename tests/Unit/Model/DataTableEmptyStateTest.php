<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Enum\Icon;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Runtime\RenderingPreparer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[CoversClass(DataTable::class)]
final class DataTableEmptyStateTest extends TestCase
{
    #[Test]
    public function it_adds_nothing_to_the_payload_when_unused(): void
    {
        $options = (new DataTable('t'))->getOptions();

        $this->assertArrayNotHasKey('emptyState', $options);
        $this->assertArrayNotHasKey('recordUrl', $options);
    }

    #[Test]
    public function it_serializes_the_empty_state(): void
    {
        $table = (new DataTable('t'))->emptyState(
            'No users yet',
            'Invite your first teammate.',
            Icon::Inbox,
            ['label' => 'Invite', 'url' => '/users/new'],
        );

        $this->assertSame([
            'heading'     => 'No users yet',
            'description' => 'Invite your first teammate.',
            'icon'        => 'inbox',
            'action'      => ['label' => 'Invite', 'url' => '/users/new'],
            'labels'      => [],
        ], $table->getOptions()['emptyState']);
    }

    #[Test]
    public function it_serializes_translated_default_labels(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => strtoupper($id));

        $table = (new DataTable('t'))->emptyState('Nothing');
        (new RenderingPreparer(translator: $translator))->prepareBeforeDataHydration($table, null);

        $this->assertSame([
            'noResults'     => 'EMPTY.NORESULTS',
            'noResultsHint' => 'EMPTY.NORESULTSHINT',
            'resetFilters'  => 'EMPTY.RESETFILTERS',
        ], $table->getOptions()['emptyState']['labels']);
    }

    /**
     * @param array<string, mixed> $action
     */
    #[Test]
    #[DataProvider('provideInvalidActions')]
    public function it_refuses_an_unsafe_or_malformed_action(array $action): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new DataTable('t'))->emptyState('Nothing', action: $action);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideInvalidActions(): iterable
    {
        yield 'javascript url' => [['label' => 'Go', 'url' => 'javascript:alert(1)']];
        yield 'data url' => [['label' => 'Go', 'url' => 'data:text/html,x']];
        yield 'protocol relative url' => [['label' => 'Go', 'url' => '//evil.example']];
        yield 'missing url' => [['label' => 'Go']];
        yield 'missing label' => [['url' => '/ok']];
        yield 'empty label' => [['label' => '', 'url' => '/ok']];
    }

    #[Test]
    public function it_flags_record_urls_without_serializing_the_callables(): void
    {
        $table = (new DataTable('t'))
            ->rowClass(static fn (): string => 'x')
            ->recordUrl(static fn (): string => '/x');

        $this->assertTrue($table->getOptions()['recordUrl']);
        $this->assertArrayNotHasKey('rowClass', $table->getOptions());
        $this->assertInstanceOf(\Closure::class, $table->getRowClassResolver());
        $this->assertInstanceOf(\Closure::class, $table->getRecordUrlResolver());
    }
}
