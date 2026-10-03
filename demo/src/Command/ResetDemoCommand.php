<?php

declare(strict_types=1);

namespace App\Command;

use App\Demo\DemoSeeder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:demo:reset', 'Recreate the demo database with fresh sample data.')]
final readonly class ResetDemoCommand
{
    public function __construct(
        private DemoSeeder $seeder,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $this->seeder->reset();

        $io->success(\sprintf('Demo data ready: 60 products, %d customers, %d orders.', DemoSeeder::CUSTOMERS, DemoSeeder::ORDERS));

        return Command::SUCCESS;
    }
}
