<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Install;

use Pentiminax\UX\DataTables\Enum\StyleFramework;

/**
 * Look offered by ux-datatables:install.
 */
enum InstallStyle: string
{
    case Tailwind   = 'tailwind';
    case DataTables = 'dt';
    case Bootstrap5 = 'bs5';

    public function framework(): StyleFramework
    {
        return self::Bootstrap5 === $this ? StyleFramework::Bootstrap5 : StyleFramework::DataTables;
    }

    /**
     * Flags of the datatable_core controller's "autoimport" in assets/controllers.json.
     *
     * @return array<string, bool>
     */
    public function autoimport(): array
    {
        return [
            '@pentiminax/ux-datatables/dist/styles/datatables-base-style.css'     => true,
            '@pentiminax/ux-datatables/dist/styles/datatables-tailwind-theme.css' => self::Tailwind === $this,
            'datatables.net-dt/css/dataTables.dataTables.min.css'                 => self::Bootstrap5 !== $this,
            'datatables.net-bs5/css/dataTables.bootstrap5.min.css'                => self::Bootstrap5 === $this,
        ];
    }
}
