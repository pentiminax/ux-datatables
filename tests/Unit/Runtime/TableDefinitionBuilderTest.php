<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Runtime;

use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Enum\SelectStyle;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\BulkAction;
use Pentiminax\UX\DataTables\Model\BulkActions;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Runtime\TableDefinitionBuilder;
use Pentiminax\UX\DataTables\Tests\Support\BuildsRuntime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(TableDefinitionBuilder::class)]
final class TableDefinitionBuilderTest extends TestCase
{
    use BuildsRuntime;

    #[Test]
    public function it_calls_the_configure_hooks_once_per_table(): void
    {
        $calls   = new \ArrayObject();
        $table   = new CountingDefinitionTable($calls);
        $runtime = self::buildRuntime();

        $first = $runtime->definition($table);
        $runtime->render($table);
        $runtime->render($table);

        $this->assertSame($first, $runtime->definition($table));
        $this->assertSame(1, $calls['configureDataTable']);
        $this->assertSame(1, $calls['configureColumns']);
    }

    #[Test]
    public function it_hands_state_set_while_configuring_to_the_hooks(): void
    {
        $table = new ConfiguredStateTable();

        $call = self::buildRuntime()->open($table);

        $this->assertSame('set-in-configure', $call->hooks->table->exposeLabel());
    }

    #[Test]
    public function it_renders_the_same_payload_twice(): void
    {
        $table   = new CountingDefinitionTable(new \ArrayObject());
        $runtime = self::buildRuntime();

        $this->assertSame($runtime->render($table)->getView(), $runtime->render($table)->getView());
    }

    #[Test]
    public function it_enables_multi_selection_for_bulk_actions(): void
    {
        $table = new CountingDefinitionTable(new \ArrayObject(), bulk: true);

        $select = self::buildRuntime(extensions: ['select' => ['style' => 'single']])
            ->definition($table)->table->getExtensionsCollection()->getSelectExtension();

        $this->assertSame(SelectStyle::MULTI, $select?->getStyle());
    }

    #[Test]
    public function it_depends_on_no_request_scoped_service(): void
    {
        $forbidden = ['RequestStack', 'TokenStorage', 'Session', 'Translator', 'AuthorizationChecker', 'Security'];

        foreach ((new \ReflectionClass(TableDefinitionBuilder::class))->getConstructor()?->getParameters() ?? [] as $parameter) {
            $type = (string) $parameter->getType();

            foreach ($forbidden as $name) {
                $this->assertStringNotContainsString($name, $type, \sprintf('The definition builder must not depend on "%s".', $name));
            }
        }
    }
}

final class CountingDefinitionTable extends AbstractDataTable
{
    /**
     * @param \ArrayObject<string, int> $calls
     */
    public function __construct(private readonly \ArrayObject $calls, private readonly bool $bulk = false)
    {
    }

    public function configureDataTable(DataTable $table): DataTable
    {
        $this->calls['configureDataTable'] = ($this->calls['configureDataTable'] ?? 0) + 1;

        return $table;
    }

    public function configureColumns(): iterable
    {
        $this->calls['configureColumns'] = ($this->calls['configureColumns'] ?? 0) + 1;

        yield TextColumn::new('name');
    }

    public function configureBulkActions(BulkActions $actions): BulkActions
    {
        return $this->bulk ? $actions->add(BulkAction::new('archive', 'Archive')) : $actions;
    }
}

final class ConfiguredStateTable extends AbstractDataTable
{
    private string $label = '';

    public function configureColumns(): iterable
    {
        $this->label = 'set-in-configure';

        yield TextColumn::new('name');
    }

    public function exposeLabel(): string
    {
        return $this->label;
    }

    protected function mapRow(mixed $row): array
    {
        return [];
    }
}
