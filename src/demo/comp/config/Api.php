<?php

namespace cryodrift\demo\comp\config;

use cryodrift\demo\Component;
use cryodrift\fw\Context;
use cryodrift\fw\HtmlUi;

class Api extends Component
{
    public function update(Context $ctx): array
    {
        return parent::update($ctx);
    }

    public function render(Context $ctx, array $data, int $page = 0, int $limit = 10): HtmlUi
    {
        return parent::render($ctx, $data);
    }


}
