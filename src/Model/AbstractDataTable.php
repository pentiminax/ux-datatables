<?php

namespace Pentiminax\UX\DataTables\Model;

use Doctrine\ORM\EntityManagerInterface;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\DataTableInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\Model\Extensions\ButtonsExtension;
use Pentiminax\UX\DataTables\Model\Extensions\ColumnControlExtension;
use Pentiminax\UX\DataTables\Model\Extensions\SelectExtension;
use Pentiminax\UX\DataTables\Model\RowMapper\ClosureRowMapper;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

abstract class AbstractDataTable implements DataTableInterface
{
    protected DataTable $table;

    protected EntityManagerInterface $em;

    public function __construct()
    {
        $this->table = $this->configureDataTable(
            new DataTable($this->getClassName())
        );

        $this->table->columns(
            iterator_to_array($this->configureColumns())
        );

        $this->table->setExtensions(
            $this->configureExtensions(new DataTableExtensions())
        );

        $buttonsExtension = $this->configureButtonsExtension(new ButtonsExtension([]));
        if ($buttonsExtension->isEnabled()) {
            $this->table->addExtension($buttonsExtension);
        }

        $columnControlExtension = $this->configureColumnControlExtension(new ColumnControlExtension());
        if ($columnControlExtension->isEnabled()) {
            $this->table->addExtension($columnControlExtension);
        }

        $selectExtension = $this->configureSelectExtension(new SelectExtension());
        if ($selectExtension->isEnabled()) {
            $this->table->addExtension($selectExtension);
        }
    }

    public function getDataTable(): DataTable
    {
        return $this->table;
    }

    public function configureColumns(): iterable
    {
        return $this->getDataTable()->getColumns();
    }

    public function configureDataTable(DataTable $table): DataTable
    {
        return $table;
    }

    public function getDataProvider(): ?DataProviderInterface
    {
        return null;
    }

    public function configureExtensions(DataTableExtensions $extensions): DataTableExtensions
    {
        return $extensions;
    }

    public function configureButtonsExtension(ButtonsExtension $extension): ButtonsExtension
    {
        return $extension;
    }

    public function configureColumnControlExtension(ColumnControlExtension $extension): ColumnControlExtension
    {
        return $extension;
    }

    public function configureSelectExtension(SelectExtension $extension): SelectExtension
    {
        return $extension;
    }

    public function fetchData(DataTableQuery $query): DataTableResult
    {
        if ($this->table->isServerSide()) {
            return $this->getDataProvider()?->fetchData($query);
        }

        $result = $this->getDataProvider()?->fetchData($query);
        $data = iterator_to_array($result->data);
        $this->table->data($data);

        return $result;
    }

    #[Required]
    public function setEntityManager(EntityManagerInterface $em): void
    {
        $this->em = $em;
    }

    protected function mapRow(mixed $item): array
    {
        return is_array($item) ? $item : get_object_vars($item);
    }

    protected function rowMapper(): RowMapperInterface
    {
        return new ClosureRowMapper(
            $this->mapRow(...)
        );
    }

    private function getClassName(): string
    {
        return (new \ReflectionClass($this))->getShortName();
    }
}