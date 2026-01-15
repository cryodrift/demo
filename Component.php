<?php

namespace cryodrift\demo;

use cryodrift\fw\Context;
use cryodrift\fw\HtmlUi;

class Component
{

    public function __construct(protected readonly string $component)
    {
    }

    public function update(Context $ctx): array
    {
        return [$this->component => []];
    }

    public function render(Context $ctx, array $data): HtmlUi
    {
        return HtmlUi::fromString('');
    }
}
