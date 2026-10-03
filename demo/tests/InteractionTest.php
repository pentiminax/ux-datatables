<?php

declare(strict_types=1);

namespace App\Tests;

use App\DataTable\BulkOrdersDataTable;
use App\DataTable\ProductActionsDataTable;
use App\Demo\DemoSeeder;
use App\Entity\Order;
use App\Entity\Product;
use App\Enum\OrderStatus;
use App\Enum\ProductStatus;
use Doctrine\ORM\EntityManagerInterface;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Security\MutationTokenValidator;
use Pentiminax\UX\DataTables\Test\DataTableTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Drives the bundle's mutation endpoints the way the demo pages do, so a change that breaks editing,
 * deleting, bulk actions, or the reset button fails CI instead of the next visitor.
 *
 * @internal
 */
final class InteractionTest extends DataTableTestCase
{
    private KernelBrowser $client;
    private string $mutationToken;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        self::getContainer()->get(DemoSeeder::class)->reset();

        $crawler             = $this->client->request('GET', '/en/actions');
        $view                = json_decode((string) $crawler->filter('table[data-controller]')->attr('data-pentiminax--ux-datatables--datatable-view-value'), true, flags: \JSON_THROW_ON_ERROR);
        $this->mutationToken = $view['csrfToken'];
    }

    public function test_switch_toggles_featured_flag(): void
    {
        $product = $this->product(ProductStatus::Active);

        $this->mutate('PATCH', '/datatables/ajax/edit', ProductActionsDataTable::class, [
            'id'       => $product->id,
            'field'    => 'featured',
            'newValue' => !$product->featured,
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame(!$product->featured, $this->reload($product)->featured);
    }

    public function test_edit_form_shows_and_saves_the_price_in_euros(): void
    {
        $product        = $this->product(ProductStatus::Active);
        $product->price = 2500;
        $this->entityManager()->flush();

        $this->mutate('POST', '/datatables/ajax/edit-form/view', ProductActionsDataTable::class, ['id' => $product->id]);

        self::assertResponseIsSuccessful();
        $html = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR)['html'];
        self::assertStringContainsString('value="25.00"', $html);

        $this->mutate('POST', '/datatables/ajax/edit-form', ProductActionsDataTable::class, [
            'id'       => $product->id,
            'formData' => [
                'name'     => $product->name,
                'category' => $product->category->value,
                'price'    => '30.5',
                'stock'    => (string) $product->stock,
                'status'   => $product->status->value,
                '_token'   => 'csrf-token',
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame(3050, $this->reload($product)->price);
    }

    public function test_voter_only_lets_archived_products_be_deleted(): void
    {
        $active   = $this->product(ProductStatus::Active);
        $archived = $this->product(ProductStatus::Archived);

        $this->mutate('DELETE', '/datatables/ajax/delete', ProductActionsDataTable::class, ['id' => $active->id]);
        self::assertResponseStatusCodeSame(403);

        $this->mutate('DELETE', '/datatables/ajax/delete', ProductActionsDataTable::class, ['id' => $archived->id]);
        self::assertResponseIsSuccessful();
        self::assertNull($this->entityManager()->find(Product::class, $archived->id));
    }

    public function test_bulk_ship_skips_orders_that_are_not_paid(): void
    {
        $paid    = $this->order(OrderStatus::Paid);
        $pending = $this->order(OrderStatus::Pending);

        $this->mutate('POST', '/datatables/ajax/bulk', BulkOrdersDataTable::class, [
            'action' => 'ship',
            'ids'    => [(string) $paid->id, (string) $pending->id],
        ]);

        self::assertResponseIsSuccessful();
        $result = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(1, $result['processed']);
        self::assertSame(1, $result['skipped']);
        self::assertSame(OrderStatus::Shipped, $this->reload($paid)->status);
        self::assertSame(OrderStatus::Pending, $this->reload($pending)->status);
    }

    public function test_bulk_cancel_skips_orders_the_user_may_not_cancel(): void
    {
        $paid      = $this->order(OrderStatus::Paid);
        $delivered = $this->order(OrderStatus::Delivered);

        $this->mutate('POST', '/datatables/ajax/bulk', BulkOrdersDataTable::class, [
            'action' => 'cancel',
            'ids'    => [(string) $paid->id, (string) $delivered->id],
        ]);

        self::assertResponseIsSuccessful();
        $result = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(1, $result['processed']);
        self::assertSame(1, $result['skipped']);
        self::assertSame(OrderStatus::Cancelled, $this->reload($paid)->status);
        self::assertSame(OrderStatus::Delivered, $this->reload($delivered)->status);
    }

    public function test_reset_button_restores_the_seeded_data(): void
    {
        $archived = $this->product(ProductStatus::Archived);
        $this->entityManager()->remove($archived);
        $this->entityManager()->flush();

        $crawler = $this->client->request('GET', '/en/actions');
        $this->client->submit($crawler->filter('.reset-form')->form());

        self::assertResponseRedirects('/en/actions');
        self::assertSame(60, $this->entityManager()->getRepository(Product::class)->count());
        self::assertSame(DemoSeeder::ORDERS, $this->entityManager()->getRepository(Order::class)->count());
    }

    /**
     * @param class-string<AbstractDataTable> $table
     * @param array<string, mixed>            $payload
     */
    private function mutate(string $method, string $path, string $table, array $payload): void
    {
        $this->client->request(
            $method,
            $path,
            server: [
                'CONTENT_TYPE'                                                            => 'application/json',
                'HTTP_X_REQUESTED_WITH'                                                   => 'XMLHttpRequest',
                'HTTP_'.strtoupper(str_replace('-', '_', MutationTokenValidator::HEADER)) => $this->mutationToken,
            ],
            content: json_encode(['dataTable' => $this->dataTableRegistry()->getActionToken($table)] + $payload, \JSON_THROW_ON_ERROR),
        );
    }

    private function product(ProductStatus $status): Product
    {
        return $this->entityManager()->getRepository(Product::class)->findOneBy(['status' => $status]);
    }

    private function order(OrderStatus $status): Order
    {
        return $this->entityManager()->getRepository(Order::class)->findOneBy(['status' => $status]);
    }

    /**
     * @template T of object
     *
     * @param T $entity
     *
     * @return T
     */
    private function reload(object $entity): object
    {
        $this->entityManager()->clear();

        return $this->entityManager()->find($entity::class, $entity->id);
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
