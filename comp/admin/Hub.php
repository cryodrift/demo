<?php

namespace cryodrift\demo\comp\admin;

use cryodrift\demo\Component;
use cryodrift\demo\db\Repository;
use cryodrift\fw\Context;
use cryodrift\fw\Core;
use cryodrift\fw\HtmlUi;

class Hub extends Component
{
    protected array $data = [];

    public function __construct(string $component, protected readonly Repository $db)
    {
        $tpldir = __DIR__ . '/ui/';
        parent::__construct($component, $tpldir);
    }

    public function update(Context $ctx, array $params = []): array
    {
        $compname = $this->component;
        if (method_exists($this->db, $compname)) {
            $params = Core::getParams($this->db, $compname, $params, $ctx, false);
            $this->data = $this->db->$compname(...$params);
            return $this->data;
        } else {
            return parent::update($ctx, $params);
        }
    }

    public function render(Context $ctx, array $params): HtmlUi
    {
        $component = $this->component;
        $ui = parent::render($ctx, $params);
        if (empty($this->data)) {
            if (method_exists($this->db, $component)) {
                $ui->setAttributes($this->db->$component(...Core::getParams($this->db, $component, $params, $ctx)), true);
            } else {
                $ui->setAttributes([$component => [[]]]);
            }
        } else {
            $ui->setAttributes($this->data, true);
        }

        return $ui;
    }
}
