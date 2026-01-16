<?php

namespace cryodrift\demo\db;

use cryodrift\fw\Config;
use cryodrift\fw\Context;

trait Comp_checkout
{
    public function cont_checkout(): array
    {
        $out = [];
        $out['placeorder'] = [];
        $out['loginbox'] = [[]];
        if ($this->userid) {
            $out['placeorder'] = [[]];
            $out['loginbox'] = [];
        }

        $out['cont_checkout'] = [[]];

        return $out;
    }
}
