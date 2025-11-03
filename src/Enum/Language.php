<?php

namespace Pentiminax\UX\DataTables\Enum;

enum Language: string
{
    private const DATATABLES_VERSION = '2.2.2';

    case EN = 'en-GB';
    case FR = 'fr-FR';
    case DE = 'de-DE';
    case ES = 'es-ES';

    public function getUrl(): string
    {
        return sprintf('https://cdn.datatables.net/plug-ins/%s/i18n/%s.json', self::DATATABLES_VERSION, $this->value);
    }
}
