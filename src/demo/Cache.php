<?php

//declare(strict_types=1);

namespace cryodrift\demo;

use cryodrift\fw\Core;
use cryodrift\fw\FileCache;

class Cache extends FileCache
{
    public function key2dir(string $key): string
    {
        $parts = str_split($key, 3);
        $first = array_slice($parts, 0, 3);
        $rest = array_slice($parts, 3);
        $out = implode('/', $first) . '/' . implode('', $rest);
//        Core::echo(__METHOD__, $key, $out);
        return $out;
    }

    public function getDir(): string
    {
        return $this->cachedir;
    }
}
