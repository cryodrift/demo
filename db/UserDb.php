<?php

namespace cryodrift\demo\db;

use cryodrift\fw\Core;
use cryodrift\fw\tool\DbHelperStatic;

class UserDb extends DbHelperStatic
{
    private array $data = [];
    const string DBFILE = 'demo.sqlite';

    public function __construct(string $userid, string $storage)
    {
        $this->connect('sqlite:' . $storage . $userid . '/' . self::DBFILE);
        $data = $this->runSelect('account')->fetch();
        if ($data) {
            $this->data = $data;
        }
    }

    public function getName(): string
    {
        return Core::getValue('name', $this->data);
    }

    public function getEmail(): string
    {
        return Core::getValue('email', $this->data);
    }

    public function getRole(): string
    {
        return Core::getValue('role', $this->data);
    }

    public function save(array $data): void
    {
        $this->data = Core::extractKeys($data, ['name', 'email', 'role']);
        $this->runUpdate(1, 'account', array_keys($this->data), $this->data);
    }

}
