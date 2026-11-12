<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Runtime;

use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\DataProvider\AutoDataProviderFactory;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Runtime\DataTableRuntimeFactory;
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\SummaryNote;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\SummaryOrder;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\SummaryRegion;
use Pentiminax\UX\DataTables\Tests\Support\BuildsEntityManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Who may see a column is decided once per request, not once per consumer.
 *
 * @internal
 */
#[CoversClass(DataTableRuntimeFactory::class)]
final class DataTableRuntimeVisibleColumnsTest extends TestCase
{
    use BuildsEntityManager;

    #[Test]
    public function a_server_side_draw_asks_the_voters_once_per_permissioned_column(): void
    {
        $em = $this->createEntityManager(SummaryOrder::class, SummaryNote::class, SummaryRegion::class);
        $em->persist(new SummaryOrder(1, 'paid', 1000));
        $em->persist(new SummaryOrder(2, 'paid', 2000));
        $em->flush();

        $decisions = new \ArrayObject();
        $symfony   = new class($decisions) implements AuthorizationCheckerInterface {
            public function __construct(private \ArrayObject $decisions)
            {
            }

            public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
            {
                $this->decisions[] = $attribute;

                return 'ROLE_HR' !== $attribute;
            }
        };

        $runtime = (new DataTableRuntimeFactory(
            autoDataProviderFactory: new AutoDataProviderFactory($em),
            permissionChecker: new AuthorizationChecker($symfony),
        ))->createRuntime(
            table: (new DataTable('orders'))->serverSide(),
            columns: [
                TextColumn::new('status')->field('status')->permission('ROLE_USER'),
                NumberColumn::new('amount')->field('amount')->permission('ROLE_HR'),
            ],
            asDataTable: new AsDataTable(entityClass: SummaryOrder::class),
            baseMapper: static fn (SummaryOrder $order): array => ['status' => $order->status, 'amount' => $order->amount],
            manualDataProviderFactory: static fn (): null => null,
            configureQueryBuilder: static fn ($qb) => $qb,
        );
        $runtime->handleRequest(new Request(query: ['draw' => 1]));

        $rows = json_decode((string) $runtime->getResponse()->getContent(), true)['data'];

        $this->assertSame([['status' => 'paid'], ['status' => 'paid']], $rows);
        $this->assertSame(['ROLE_USER' => 1, 'ROLE_HR' => 1], array_count_values($decisions->getArrayCopy()));
    }
}
