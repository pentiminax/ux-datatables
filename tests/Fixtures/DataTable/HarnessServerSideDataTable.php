<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\DataTable;

use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\DataProvider\ArrayDataProvider;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;

final class HarnessServerSideDataTable extends AbstractDataTable
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->serverSide();
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
        yield TextColumn::new('title');
    }

    protected function createDataProvider(): ?DataProviderInterface
    {
        return new ArrayDataProvider(
            [
                new HarnessBook(1, 'Symfony 7'),
                new HarnessBook(2, 'UX in Action'),
                new HarnessBook(3, 'Doctrine Deep Dive'),
            ],
            $this->createRowMapper(),
            $this->getResolvedColumns(),
        );
    }
}

final readonly class HarnessBook
{
    public function __construct(
        private int $id,
        private string $title,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }
}
