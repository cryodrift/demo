<?php

namespace cryodrift\demo;

use cryodrift\fw\Context;
use cryodrift\fw\Core;
use cryodrift\fw\HtmlUi;
use cryodrift\fw\Main;

abstract class Component
{

    public function __construct(protected readonly string $component, protected string $tpldir)
    {
    }

    public function update(Context $ctx, array $params): array
    {
        return [$this->component => []];
    }

    public function render(Context $ctx, array $params): HtmlUi
    {
        $ui = HtmlUi::fromString(Core::fileReadOnce(Main::path($this->tpldir . $this->component . '.html')), $this->component);
        $ui->setAttributes($params);
        return $ui;
    }
}
