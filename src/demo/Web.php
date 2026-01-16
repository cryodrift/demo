<?php

//declare(strict_types=1);

namespace cryodrift\demo;

use cryodrift\demo\db\Repository;
use cryodrift\fw\Config;
use cryodrift\fw\Context;
use cryodrift\fw\interface\Handler;
use cryodrift\fw\trait\OutHelper;

/**
 * This is for Partials and site retrieval
 */
class Web implements Handler
{
    use OutHelper;
    use ComponentHelper;

    public function __construct(
      private readonly Repository $db,
      private readonly Config $config,
      private readonly Translations $translations,
      private readonly ComponentConfig $compconfig,
      private readonly string $tpldir
    ) {
    }

    public function handle(Context $ctx): Context
    {
        $ctx->setLanguage($this->translations->getLanguages(), $this->config->defaultlang);
        $this->translations->setLang($ctx->language());
        return $this->handlePage($ctx);
    }

}

