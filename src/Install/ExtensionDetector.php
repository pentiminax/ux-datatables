<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Install;

use Pentiminax\UX\DataTables\Enum\Feature;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * Finds the extensions the declared tables use. It reads the built table instead of calling
 * getOptions(), which can trigger deprecations.
 *
 * @internal
 */
final class ExtensionDetector
{
    public function __construct(private readonly ServiceProviderInterface $tables)
    {
    }

    public function detect(): ExtensionDetection
    {
        $found    = [];
        $failures = [];

        foreach (array_keys($this->tables->getProvidedServices()) as $id) {
            try {
                $table = $this->tables->get($id);

                if (!$table instanceof AbstractDataTable) {
                    continue;
                }

                $dataTable = $table->getConfiguredDataTable();
            } catch (\Exception $e) {
                $failures[$id] = $e::class.': '.$e->getMessage();

                continue;
            }

            $keys = array_keys($dataTable->getExtensionsCollection()->all());

            foreach (PackageCatalog::selectable() as $name) {
                $key   = PackageCatalog::optionKey($name);
                $value = $dataTable->getOption($key);

                if (null !== $value && false !== $value) {
                    $keys[] = $key;
                }
            }

            $layout = $dataTable->getOption('layout');

            foreach ([Feature::BUTTONS, Feature::SEARCH_BUILDER] as $feature) {
                if ($dataTable->layoutDeclares($layout, $feature)) {
                    $keys[] = $feature->value;
                }
            }

            foreach ($keys as $key) {
                $name = PackageCatalog::fromOptionKey($key);

                if (null !== $name) {
                    $found[$name] = true;
                }
            }
        }

        $extensions = array_values(array_filter(PackageCatalog::selectable(), static fn (string $name): bool => isset($found[$name])));

        return new ExtensionDetection($extensions, $failures);
    }
}
