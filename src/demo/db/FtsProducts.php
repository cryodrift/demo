<?php

namespace cryodrift\demo\db;

use cryodrift\fw\trait\DbHelper;
use cryodrift\fw\trait\DbHelperFts;

class FtsProducts
{
    use DbHelperFts;
    use DbHelper;

    private bool $connected = false;

    public function __construct(private readonly string $storagedir)
    {
    }

    public function ftsConnect(Repository $db): void
    {
        if (!$this->connected) {
            $this->pdo = $db->getPdo();
            $this->ftsSetup($this->storagedir, 'products', 'fts' . $db->getDbname(), ['details', 'slug', 'created', 'cartid', 'price']);
            $this->ftsAttach();
            $this->connected = true;
        }
    }

}
