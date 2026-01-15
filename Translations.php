<?php

namespace cryodrift\demo;

use cryodrift\fw\Core;

class Translations
{

    public function __construct(
      private readonly array $translations,
      private array $formerrors,
      private string $lang = 'en'
    ) {
        $this->setLang($lang);
    }

    public function getLang(): string
    {
        return $this->lang;
    }


    public function setLang(string $lang): void
    {
        if (in_array($lang, $this->getLanguages())) {
            $this->lang = $lang;
        } else {
            throw new \Exception('Unknown Language for Translations');
        }
    }

    public function getLanguages(): array
    {
        return array_keys($this->translations);
    }

    public function translations(): array
    {
        return $this->translations;
    }

    public function translation(): array
    {
        return Core::getValue($this->lang, $this->translations());
    }

    public function translate(string $key): string
    {
        $translations = $this->translation();
        return Core::getValue($key, $translations, $key);
    }

    public function keys(): array
    {
        return array_keys($this->translation());
    }

    public function values(): array
    {
        return array_values($this->translation());
    }

    public function getOptions(): array
    {
        $out = [];
        foreach ($this->translations as $key => $value) {
            $option = [];
            $option['selected'] = $key === $this->lang ? 'selected' : '';
            $option['value'] = $key;
            $option['name'] = $this->translate(strtoupper($key));
            $out[] = $option;
        }
        return $out;
    }

    public function getFormErrors(): array
    {
        return Core::extractKeys($this->translation(), $this->formerrors);
    }


}
