<?php

namespace cryodrift\demo\ui;

use cryodrift\demo\Translations;
use cryodrift\fw\Core;
use cryodrift\fw\HtmlUi;

class Select extends HtmlUi
{
    public function __construct(
      string $name,
      array $options,
      string $selected,
      private readonly Translations $translations,
      string $class = 'g-mb1'
    ) {
        parent::__construct(
          '<select {{@}}attrib{{@}}{{@}}attrib{{@}} class="' . $class . '" name="' . $name . '">
                    <option value="">---</option>
                    {{@}}select{{@}}
                    <option value="{{value}}" {{selected}}>{{name}}</option>
                    {{@}}select{{@}}
                </select>'
        );
        $this->setAttributes(['select' => $this->getOptions($options, $selected)]);
    }

    public function addHtmlAttribute(string $command): self
    {
        $parts = Core::getValue('attrib', $this->getAttributes(), []);
        $parts[] = $command;
        $this->setAttributes(['attrib' => $parts], false, false);

        return $this;
    }


    protected function getOptions(array $data, string $selected): array
    {
        $out = [];
        foreach ($data as $entry) {
            $option = [];
            if (is_array($entry)) {
                $value = $entry['value'];
                $name = $entry['name'];
            } else {
                $value = $name = $entry;
            }

            $option['selected'] = $value == $selected ? 'selected' : '';
            $option['value'] = $value;
            $option['name'] = $this->translations->translate(ucfirst($name));
            $out[] = $option;
        }
        return $out;
    }


}
