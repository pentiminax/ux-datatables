<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Runtime;

use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\DataProvider\AutoDataProviderFactory;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Runtime\DataTableRuntime;
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\SummaryNote;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\SummaryOrder;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\SummaryRegion;
use Pentiminax\UX\DataTables\Tests\Support\BuildsEntityManager;
use Pentiminax\UX\DataTables\Tests\Support\BuildsRuntime;
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
#[CoversClass(DataTableRuntime::class)]
final class DataTableRuntimeVisibleColumnsTest extends TestCase
{
    use BuildsEntityManager;
    use BuildsRuntime;

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

        $table = new #[AsDataTable(entityClass: SummaryOrder::class)] class extends AbstractDataTable {
            public function configureDataTable(DataTable $table): DataTable
            {
                return $table->serverSide();
            }

            public function configureColumns(): iterable
            {
                return [
                    TextColumn::new('status')->field('status')->permission('ROLE_USER'),
                    NumberColumn::new('amount')->field('amount')->permission('ROLE_HR'),
                ];
            }

            public function mapRow(mixed $row): array
            {
                return ['status' => $row->status, 'amount' => $row->amount];
            }
        };

        $runtime  = self::buildRuntime(checker: new AuthorizationChecker($symfony), autoDataProviderFactory: new AutoDataProviderFactory($em));
        $response = $runtime->respond($table, new Request(query: ['draw' => 1]));

        $rows = json_decode((string) $response?->getContent(), true)['data'];

        $this->assertSame([['status' => 'paid'], ['status' => 'paid']], $rows);
        $this->assertSame(['ROLE_USER' => 1, 'ROLE_HR' => 1], array_count_values($decisions->getArrayCopy()));
    }
}
