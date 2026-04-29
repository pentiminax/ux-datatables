<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Form;

use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Contracts\EditModalTemplateResolverInterface;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Psr\Container\ContainerInterface;

final class EditModalTemplateResolver implements EditModalTemplateResolverInterface
{
    public function __construct(
        private readonly ContainerInterface $dataTables,
        private readonly string $defaultTemplate,
        private readonly string $defaultBodyTemplate,
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

            $attribute = $this->resolveAttribute($dataTableClass);

            if (null !== $attribute && '' !== trim($attribute->editModalTemplate)) {
                return $attribute->editModalTemplate;
            }
        }

        return $this->defaultTemplate;
    }

    public function resolveBodyTemplate(): string
    {
        return $this->defaultBodyTemplate;
    }

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

    /**
     * @var array<string, AsDataTable|null>
     */
    private static array $attributeCache = [];

    private function resolveAttribute(string $dataTableClass): ?AsDataTable
    {
        if (\array_key_exists($dataTableClass, self::$attributeCache)) {
            return self::$attributeCache[$dataTableClass];
        }

        try {
            $attributes = (new \ReflectionClass($dataTableClass))->getAttributes(AsDataTable::class);
        } catch (\ReflectionException) {
            return self::$attributeCache[$dataTableClass] = null;
        }

        return self::$attributeCache[$dataTableClass] = [] === $attributes ? null : $attributes[0]->newInstance();
    }
}
