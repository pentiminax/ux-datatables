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
                $keys      = array_keys($dataTable->getExtensionsCollection()->all());

                foreach (PackageCatalog::EXTENSIONS as $name) {
                    $key = array_search($name, PackageCatalog::OPTION_KEYS, true) ?: $name;

                    if (null !== $dataTable->getOption($key)) {
                        $keys[] = $key;
                    }
                }

                if ($this->declaresButtons($dataTable->getOption('layout'))) {
                    $keys[] = 'buttons';
                }
            } catch (\Throwable $e) {
                $failures[$id] = $e->getMessage();

                continue;
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

    private function declaresButtons(mixed $layout): bool
    {
        if (Feature::BUTTONS === $layout || Feature::BUTTONS->value === $layout) {
            return true;
        }

        if (!\is_array($layout)) {
            return false;
        }

        if (\array_key_exists(Feature::BUTTONS->value, $layout)) {
            return true;
        }

        foreach ($layout as $item) {
            if ($this->declaresButtons($item)) {
                return true;
            }
        }

        return false;
    }
}
