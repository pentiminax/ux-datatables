<?php

declare(strict_types=1);

namespace App\Demo;

use App\Enum\Category;
use App\Enum\Country;
use App\Enum\OrderStatus;
use App\Enum\ProductStatus;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Faker\Factory;
use Faker\Generator;

/**
 * Rebuilds the demo database from scratch with deterministic data.
 *
 * Rows are written through DBAL in one transaction rather than through the ORM: ten thousand
 * orders load in well under a second, which keeps the "Reset data" button usable.
 */
final readonly class DemoSeeder
{
    public const int CUSTOMERS = 500;
    public const int ORDERS    = 10_000;

    private const array PRODUCTS = [
        'audio'   => ['Headphones', 'Speaker', 'Turntable', 'Earbuds', 'Soundbar'],
        'home'    => ['Table Lamp', 'Wool Throw', 'Ceramic Vase', 'Wall Clock', 'Round Mirror'],
        'office'  => ['Desk Organizer', 'Notebook', 'Task Chair', 'Monitor Stand', 'Pen Set'],
        'outdoor' => ['Dome Tent', 'Daypack', 'Camp Lantern', 'Hammock', 'Water Bottle'],
        'kitchen' => ['Kettle', 'Knife Set', 'Coffee Grinder', 'Cutting Board', 'Teapot'],
        'travel'  => ['Duffel Bag', 'Passport Holder', 'Packing Cubes', 'Neck Pillow', 'Carry-on'],
    ];

    private const array LINES = ['Nordic', 'Atlas'];

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function reset(): void
    {
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schema   = new SchemaTool($this->entityManager);
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);

        $faker = Factory::create();
        $faker->seed(42);
        $now = new \DateTimeImmutable('today 18:00');

        $connection = $this->entityManager->getConnection();
        $connection->transactional(function (Connection $db) use ($faker, $now): void {
            $this->seedProducts($db, $faker, $now);
            $this->seedCustomers($db, $faker, $now);
            $this->seedOrders($db, $faker, $now);
        });

        $this->entityManager->clear();
    }

    private function seedProducts(Connection $db, Generator $faker, \DateTimeImmutable $now): void
    {
        $rows = [];
        foreach (self::LINES as $variant => $line) {
            foreach (self::PRODUCTS as $category => $names) {
                foreach ($names as $name) {
                    $sku = \sprintf('%s-%04d', strtoupper(substr($category, 0, 3)), $faker->unique()->numberBetween(1, 9999));

                    $rows[] = [
                        'name'           => $line.' '.$name,
                        'sku'            => $sku,
                        'category'       => Category::from($category)->value,
                        'image'          => \sprintf('/images/products/%s-%d.svg', $category, $variant + 1),
                        'price'          => $faker->numberBetween(9, 349) * 100 + 99,
                        'stock'          => $faker->boolean(85) ? $faker->numberBetween(1, 240) : 0,
                        'status'         => $faker->randomElement([ProductStatus::Active, ProductStatus::Active, ProductStatus::Active, ProductStatus::Draft, ProductStatus::Archived])->value,
                        'featured'       => (int) $faker->boolean(25),
                        'supplier_email' => $faker->companyEmail(),
                        'url'            => 'https://shop.example.com/p/'.strtolower($sku),
                        'created_at'     => $this->format($now->modify(\sprintf('-%d hours', $faker->numberBetween(2, 24 * 400)))),
                    ];
                }
            }
        }

        $this->insertAll($db, 'product', $rows);
    }

    private function seedCustomers(Connection $db, Generator $faker, \DateTimeImmutable $now): void
    {
        $rows = [];
        for ($i = 0; $i < self::CUSTOMERS; ++$i) {
            $rows[] = [
                'name'       => $faker->name(),
                'email'      => $faker->unique()->safeEmail(),
                'country'    => $faker->randomElement(Country::cases())->value,
                'vip'        => (int) $faker->boolean(15),
                'created_at' => $this->format($now->modify(\sprintf('-%d days', $faker->numberBetween(1, 1100)))),
            ];
        }

        $this->insertAll($db, 'customer', $rows);
    }

    private function seedOrders(Connection $db, Generator $faker, \DateTimeImmutable $now): void
    {
        $rows = [];
        for ($i = 1; $i <= self::ORDERS; ++$i) {
            $placedAt = $now->modify(\sprintf('-%d minutes', $faker->numberBetween(30, 365 * 24 * 60)));
            $ageDays  = (int) $now->diff($placedAt)->days;
            $status   = match (true) {
                $faker->boolean(4) => OrderStatus::Cancelled,
                $ageDays < 7       => $faker->randomElement([OrderStatus::Pending, OrderStatus::Paid]),
                $ageDays < 21      => $faker->randomElement([OrderStatus::Paid, OrderStatus::Shipped]),
                $ageDays < 40      => $faker->randomElement([OrderStatus::Shipped, OrderStatus::Delivered]),
                default            => OrderStatus::Delivered,
            };
            $shipped = \in_array($status, [OrderStatus::Shipped, OrderStatus::Delivered], true);

            $rows[] = [
                'reference'   => \sprintf('ORD-%06d', 100_000 + $i),
                'customer_id' => $faker->numberBetween(1, self::CUSTOMERS),
                'items'       => $items = $faker->numberBetween(1, 6),
                'total'       => $items * $faker->numberBetween(1_500, 18_000),
                'status'      => $status->value,
                'express'     => (int) $faker->boolean(20),
                'placed_at'   => $this->format($placedAt),
                'shipped_at'  => $shipped ? $this->format($placedAt->modify(\sprintf('+%d hours', $faker->numberBetween(20, 90)))) : null,
            ];
        }

        $this->insertAll($db, 'orders', $rows);
    }

    /**
     * Multi-row INSERTs keep the reset to a few dozen queries, so the profiler's Doctrine panel
     * stays readable and memory stays flat when the reset runs from the browser.
     *
     * @param list<array<string, mixed>> $rows
     */
    private function insertAll(Connection $db, string $table, array $rows): void
    {
        $columns     = array_keys($rows[0]);
        $placeholder = '('.implode(', ', array_fill(0, \count($columns), '?')).')';

        foreach (array_chunk($rows, 500) as $chunk) {
            $db->executeStatement(
                \sprintf('INSERT INTO %s (%s) VALUES %s', $table, implode(', ', $columns), implode(', ', array_fill(0, \count($chunk), $placeholder))),
                array_merge(...array_map(array_values(...), $chunk)),
            );
        }
    }

    private function format(\DateTimeImmutable $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
