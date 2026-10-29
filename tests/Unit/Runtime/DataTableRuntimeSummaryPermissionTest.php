<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Runtime;

use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\DataProvider\AutoDataProviderFactory;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Summary\Count;
use Pentiminax\UX\DataTables\Model\Summary\Sum;
use Pentiminax\UX\DataTables\Runtime\DataTableRuntimeFactory;
use Pentiminax\UX\DataTables\Runtime\RenderingPreparer;
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\SummaryNote;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\SummaryOrder;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\SummaryRegion;
use Pentiminax\UX\DataTables\Tests\Support\BuildsEntityManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A summary must never reveal a column the user may not see.
 *
 * @internal
 */
#[CoversClass(DataTableRuntimeFactory::class)]
#[CoversClass(RenderingPreparer::class)]
final class DataTableRuntimeSummaryPermissionTest extends TestCase
{
    use BuildsEntityManager;

    #[Test]
    public function a_denied_summarized_column_is_absent_from_the_response(): void
    {
        $payload = $this->payload(granted: false);

        $this->assertSame(['status' => ['count' => 2]], $payload['summaries']);
    }

    #[Test]
    public function a_granted_summarized_column_is_present(): void
    {
        $payload = $this->payload(granted: true);

        $this->assertSame(3000, $payload['summaries']['amount']['sum']);
    }

    #[Test]
    public function the_summary_labels_are_not_sent_when_only_a_denied_column_is_summarized(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('label');
        $table = (new DataTable('Test'))->add(NumberColumn::new('amount')->setPermission('ROLE_HR')->summarize(new Sum()));

        (new RenderingPreparer(translator: $translator, columnResolver: new ColumnResolver(permissionChecker: $this->checker(false))))->prepare($table, null);

        $this->assertArrayNotHasKey('summaryLabels', $table->getOptions());
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(bool $granted): array
    {
        $em = $this->createEntityManager(SummaryOrder::class, SummaryNote::class, SummaryRegion::class);
        $em->persist(new SummaryOrder(1, 'paid', 1000));
        $em->persist(new SummaryOrder(2, 'paid', 2000));
        $em->flush();

        $columns = [
            TextColumn::new('status')->setField('status')->summarize(new Count()),
            NumberColumn::new('amount')->setField('amount')->setPermission('ROLE_HR')->summarize(new Sum()),
        ];
        $permissionChecker = $this->checker($granted);

        $runtime = (new DataTableRuntimeFactory(
            autoDataProviderFactory: new AutoDataProviderFactory($em),
            permissionChecker: $permissionChecker,
        ))->createRuntime(
            table: new DataTable('orders'),
            columns: $columns,
            asDataTable: new AsDataTable(entityClass: SummaryOrder::class),
            baseMapper: static fn (): array => [],
            manualDataProviderFactory: static fn (): null => null,
            configureQueryBuilder: static fn ($qb) => $qb,
        );
        $runtime->handleRequest(new Request(query: ['draw' => 1]));

        return json_decode((string) $runtime->getResponse()->getContent(), true);
    }

    private function checker(bool $granted): AuthorizationChecker
    {
        $symfony = $this->createStub(AuthorizationCheckerInterface::class);
        $symfony->method('isGranted')->willReturn($granted);

        return new AuthorizationChecker($symfony);
    }
}
