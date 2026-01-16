<?php

namespace cryodrift\demo\ui;

use cryodrift\fw\HtmlUi;

class Checkbox extends HtmlUi
{
    public function __construct(
      string $name,
      bool $checked
    ) {
        parent::__construct(
          '<input type="hidden" name="{{name}}" value="0">
          ' . '<input type="checkbox" name="{{name}}" value="1" {{checked}}>'
        );
        $this->setAttributes([
          'name' => $name,
          'checked' => $checked ? 'checked' : '',
        ]);
    }

}
