<?php

namespace cryodrift\demo\ui;

use cryodrift\fw\Core;
use cryodrift\fw\HtmlUi;

class Form extends HtmlUi
{

    public function __construct(string $action = '')
    {
        parent::__construct('<form method="post" action="{{action}}" {{@}}attrib{{@}}{{@}}attrib{{@}}>{{@}}parts{{@}}{{@}}parts{{@}}</form>');
        $this->setAttributes(['action' => $action]);
    }

    public function addInputHidden(string $name, string $value): self
    {
        $inp = HtmlUi::fromString('<input type="hidden" name="{{name}}" value="{{value}}">')->setAttributes(['name' => $name, 'value' => $value]);
        $parts = Core::getValue('parts', $this->getAttributes(), []);
        $parts[] = $inp;
        $this->setAttributes(['parts' => $parts]);
        return $this;
    }

    public function addHtmlUi(HtmlUi $hui): self
    {
        $parts = Core::getValue('parts', $this->getAttributes(), []);
        $parts[] = $hui;
        $this->setAttributes(['parts' => $parts]);

        return $this;
    }

    public function addHtmlAttribute(string $attribute): self
    {
        $parts = Core::getValue('attrib', $this->getAttributes(), []);
        $parts[] = $attribute;
        $this->setAttributes(['attrib' => $parts], false, false);

        return $this;
    }


}
