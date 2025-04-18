<?php

namespace Pentiminax\UX\DataTables\Model\Options;

readonly class AjaxOption
{
    public function __construct(
        private string $url,
        private ?string $dataSrc = null,
        private ?string $type = null,
    ) {
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getDataSrc(): ?string
    {
        return $this->dataSrc;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function toArray(): array
    {
        $array = [
            'url' => $this->url,
        ];

        if ($this->dataSrc !== null) {
            $array['dataSrc'] = $this->dataSrc;
        }

        if ($this->type !== null) {
            $array['type'] = $this->type;
        }

        return $array;
    }
}