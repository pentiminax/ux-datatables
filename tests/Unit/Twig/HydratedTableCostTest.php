<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Twig;

use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\DataProvider\ArrayDataProvider;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\DataTableResult;
use Pentiminax\UX\DataTables\Profiler\DataTableProfiler;
use Pentiminax\UX\DataTables\Runtime\DataTableInfrastructure;
use Pentiminax\UX\DataTables\Tests\Support\CollectsDeprecations;
use Pentiminax\UX\DataTables\Twig\DataTablesExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\UX\StimulusBundle\Helper\StimulusHelper;

/**
 * What an application observes when a table embeds its rows in the page.
 *
 * @internal
 */
#[CoversClass(AbstractDataTable::class)]
#[CoversClass(DataTablesExtension::class)]
final class HydratedTableCostTest extends TestCase
{
    use CollectsDeprecations;

    protected function setUp(): void
    {
        (new \ReflectionProperty(AbstractDataTable::class, 'implicitClientSideReported'))->setValue(null, []);
    }

    #[Test]
    public function an_entity_table_without_an_explicit_choice_is_deprecated_once_per_class(): void
    {
        $first  = $this->implicitDefault($this->deprecationsOf(fn () => $this->render(new ImplicitHydrationTable())));
        $second = $this->implicitDefault($this->deprecationsOf(fn () => $this->render(new ImplicitHydrationTable())));

        $this->assertCount(1, $first);
        $this->assertStringContainsString(ImplicitHydrationTable::class, $first[0]);
        $this->assertStringContainsString('2.0', $first[0]);
        $this->assertStringContainsString('serverSide(false)', $first[0]);
        $this->assertSame([], $second);
    }

    #[Test]
    public function an_explicit_client_side_table_is_not_deprecated_and_still_embeds_its_rows(): void
    {
        $rendered = '';
        $messages = $this->implicitDefault($this->deprecationsOf(function () use (&$rendered): void {
            $rendered = $this->render(new ExplicitHydrationTable());
        }));

        $this->assertSame([], $messages);
        $this->assertStringContainsString('Ada', html_entity_decode($rendered));
    }

    #[Test]
    public function hydration_asks_the_provider_for_rows_only(): void
    {
        $table = new RecordingHydrationTable();
        $this->render($table);

        $this->assertTrue($table->rowsOnly);
    }

    #[Test]
    public function a_table_above_the_threshold_logs_one_warning_naming_the_class_the_count_and_the_fix(): void
    {
        $logger = $this->logger();
        $this->render(new ExplicitHydrationTable(), threshold: 1, logger: $logger);

        $this->assertCount(1, $logger->records);
        [$level, $message] = $logger->records[0];
        $this->assertSame('warning', $level);
        $this->assertStringContainsString(ExplicitHydrationTable::class, $message);
        $this->assertStringContainsString('2 rows', $message);
        $this->assertStringContainsString('serverSide()', $message);
        $this->assertStringNotContainsString('Ada', $message);
    }

    #[Test]
    public function a_zero_threshold_disables_the_warning(): void
    {
        $logger = $this->logger();
        $this->render(new ExplicitHydrationTable(), threshold: 0, logger: $logger);

        $this->assertSame([], $logger->records);
    }

    #[Test]
    public function a_table_at_the_threshold_is_not_warned_about(): void
    {
        $logger = $this->logger();
        $this->render(new ExplicitHydrationTable(), threshold: 2, logger: $logger);

        $this->assertSame([], $logger->records);
    }

    #[Test]
    public function the_profiler_records_the_mode_the_row_count_and_the_payload_size(): void
    {
        $profiler = new DataTableProfiler(hydrationRowThreshold: 1);
        $rendered = $this->render(new ExplicitHydrationTable(), profiler: $profiler);

        $entry = $profiler->getRenderedTables()[0];

        $this->assertSame('ClientHydrated', $entry['dataSourceMode']);
        $this->assertSame(2, $entry['rowCount']);
        $this->assertSame(\strlen($rendered), $entry['payloadBytes']);
        $this->assertTrue($entry['hydrationOverThreshold']);
    }

    /**
     * @param list<string> $deprecations
     *
     * @return list<string>
     */
    private function implicitDefault(array $deprecations): array
    {
        return array_values(array_filter($deprecations, static fn (string $message): bool => str_contains($message, 'never called serverSide()')));
    }

    private function logger(): AbstractLogger
    {
        return new class extends AbstractLogger {
            /** @var list<array{string, string}> */
            public array $records = [];

            public function log($level, $message, array $context = []): void
            {
                $this->records[] = [(string) $level, (string) $message];
            }
        };
    }

    private function render(AbstractDataTable $table, int $threshold = 0, ?AbstractLogger $logger = null, ?DataTableProfiler $profiler = null): string
    {
        $table->setDataTableInfrastructure(DataTableInfrastructure::createDefault(
            profiler: $profiler,
            logger: $logger,
            hydrationRowThreshold: $threshold,
        ));

        return (new DataTablesExtension(new StimulusHelper(null), new ColumnResolver(), profiler: $profiler))->renderDataTable($table);
    }
}

abstract class HydrationTestTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield TextColumn::new('name');
    }

    protected function createDataProvider(): ?DataProviderInterface
    {
        return new ArrayDataProvider([['name' => 'Ada'], ['name' => 'Grace']], $this->createRowMapper(), $this->getResolvedColumns());
    }
}

#[AsDataTable(entityClass: \stdClass::class)]
final class ImplicitHydrationTable extends HydrationTestTable
{
}

#[AsDataTable(entityClass: \stdClass::class)]
final class ExplicitHydrationTable extends HydrationTestTable
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->serverSide(false);
    }
}

#[AsDataTable(entityClass: \stdClass::class)]
final class RecordingHydrationTable extends HydrationTestTable
{
    public ?bool $rowsOnly = null;

    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->serverSide(false);
    }

    protected function createDataProvider(): ?DataProviderInterface
    {
        $inner = parent::createDataProvider();

        return new class($inner, $this) implements DataProviderInterface {
            public function __construct(private readonly DataProviderInterface $inner, private readonly RecordingHydrationTable $table)
            {
            }

            public function fetchData(DataTableRequest $request): DataTableResult
            {
                $this->table->rowsOnly = $request->rowsOnly;

                return $this->inner->fetchData($request);
            }
        };
    }
}
