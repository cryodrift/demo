<?php

//declare(strict_types=1);

namespace cryodrift\demo;

use cryodrift\fw\FileCache;
use cryodrift\fw\interface\Cachegroup;

class FakeCache implements Cachegroup
{

    public function setGroup(string $name, string $key, string $value): void
    {
        // TODO: Implement setGroup() method.
    }

    public function getGroup(string $name, string $key, $default = ''): string
    {
        return $default;
    }

    public function deleteGroup(string $name, string $key): bool
    {
        return true;
    }

    public function hasGroup(string $name, string $key): bool
    {
        return false;
    }

    public function clearGroup(string $name): bool
    {
        return true;
    }
}
