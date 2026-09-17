<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Form;

use Pentiminax\UX\DataTables\Attribute\AsDataTableResolver;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Psr\Container\ContainerInterface;

class EditModalTemplateResolver
{
    public function __construct(
        private readonly ContainerInterface $dataTables,
        private readonly string $defaultTemplate,
        private readonly string $defaultBodyTemplate,
        private readonly AsDataTableResolver $asDataTableResolver = new AsDataTableResolver(),
    ) {
    }

    public function resolveChromeTemplate(?string $dataTableClass): string
    {
        if (null !== $dataTableClass && $this->dataTables->has($dataTableClass)) {
            $dataTable = $this->dataTables->get($dataTableClass);

            if ($dataTable instanceof AbstractDataTable) {
                $template = $dataTable->getConfiguredDataTable()->getEditModalTemplate();

                if (\is_string($template) && '' !== trim($template)) {
                    return $template;
                }
            }

            $template = $this->asDataTableResolver->resolve($dataTableClass)?->editModalTemplate;

            if (null !== $template && '' !== trim($template)) {
                return $template;
            }
        }

        return $this->defaultTemplate;
    }

    public function resolveBodyTemplate(): string
    {
        return $this->defaultBodyTemplate;
    }

    /**
     * @return ColumnInterface[]
     */
    public function resolveColumns(string $dataTableClass): array
    {
        if (!$this->dataTables->has($dataTableClass)) {
            throw new \RuntimeException(\sprintf('DataTable "%s" is not registered.', $dataTableClass));
        }

        $dataTable = $this->dataTables->get($dataTableClass);

        if (!$dataTable instanceof AbstractDataTable) {
            throw new \RuntimeException(\sprintf('"%s" must extend AbstractDataTable.', $dataTableClass));
        }

        return array_values($dataTable->getConfiguredDataTable()->getColumns());
    }
}
