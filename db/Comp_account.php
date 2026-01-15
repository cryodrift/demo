<?php

namespace cryodrift\demo\db;

use cryodrift\demo\Web;
use cryodrift\fw\Context;
use cryodrift\fw\Core;

trait Comp_account
{


    public function comp_account(): array
    {
        $data = $this->account();
        $data = $data ?: array_flip($this->columns('user.account'));
        $out = [];
        $out['comp_account'] = [$data];
        return $out;
    }

    public function comp_account_edit(Context $ctx, array $data = []): array
    {
        $out = [];
        if (!empty($data)) {
            Core::echo(__METHOD__, $data);
            $data = $this->modAccount($data);
        } else {
            Core::echo(__METHOD__, 'no data given');
            $data = $this->account();
        }
        if (!$data) {
            $data = array_fill_keys($this->columns('user.account'), '');
        }

        $out['comp_account_edit'] = [$data];
        return $out;
    }

    public function comp_address(Context $ctx, array $data = []): array
    {
        $adrid = Core::getValue('id', $data);
        switch (Core::getValue($this->config->actionmethod, $data)) {
            case'sel':
                $this->query("UPDATE user.address SET selected = ''");
                $this->modItem('user.address', ['id' => $adrid, 'selected' => 'selected']);
                $adr = $this->item('user.address', $adrid);
                break;
            case'mod':
                $data['type'] = self::ADR_TYP_DELIVER;
                $adrid = $this->modItem('user.address', $data);
                $adr = $this->item('user.address', $adrid);
                break;
            default:
                $adr = $this->runSelect('user.address', ['selected'], ['selected' => 'selected'])->fetch();
        }

        $select = Core::addData($this->items('user.address'), function ($adr) use ($adrid) {
            $out = [];
            $out['selected'] = $adr['selected'];
            $out['value'] = $adr['id'];
            $out['html'] = implode(', ', array_values(Core::removeKeys(['created', 'changed', 'selected', 'id'], $adr)));
            return $out;
        });

        $out = [];

        if (!empty($select)) {
            $out['select'] = $select;
        } else {
            $out['select'] = [];
        }
        if (empty($adr)) {
            $adr = [
              'name' => '',
              'street' => '',
              'ort' => '',
              'country' => '',
              'plz' => '',
              'id' => '',
            ];
        }
        $out['comp_address'] = [$adr];
//        Core::echo(__METHOD__, $out);
        return $out;
    }

    public function address(string $type = self::ADR_TYP_DELIVER): array
    {
        $where = ['selected' => 'selected', 'type' => $type];
        return Core::pop($this->runSelect('user.address', array_keys($where), $where)->fetchAll());
    }

    public function account(): array
    {
        $out = ['role' => 'unknown'];
        if ($this->ctx->hasUser()) {
            $out = $this->runSelect('user.account', ['id'], ['id' => 1])->fetch() ?: [];
        }
        return $out;
    }

    protected function modAccount(array $data): array
    {
        $data['id'] = 1;
        if (empty($this->account())) {
            unset($data['id']);
        }
        $this->modItem('user.account', $data);
        return $this->account();
    }
}
