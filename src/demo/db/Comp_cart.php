<?php

namespace cryodrift\demo\db;

use cryodrift\fw\Context;
use cryodrift\fw\Core;
use Exception;

trait Comp_cart
{
    use RepositoryBase;

    public function cont_cart(Context $ctx): array
    {
        $cart = $ctx->request()->vars('cart');
        if (!$cart && $this->userid) {
            $cart = Core::value('cartids', $this->cart());
        }
        $data = $this->getCartIds($cart);

        $out = [
          'emptyinfo' => [],
          'buttons' => [[]],
          'nouserinfo' => [],
          'cont_cart' => [[]]
        ];
        if (empty($data)) {
            $out['emptyinfo'] = [[]];
            $out['buttons'] = [];
            $out['nouserinfo'] = [];
        }
//        Core::echo(__METHOD__, $data);
        return $out;
    }

    public function comp_cart(Context $ctx, array $data = []): array
    {
        $cart = $ctx->request()->vars('cart');
        if (!$cart && $this->userid) {
            $cart = Core::value('cartids', $this->cart());
        }
        $cartids = $this->getCartIds($cart);
        $method = strtolower(Core::getValue($this->config->actionmethod, $data));
        $amount = (int)Core::getValue('amount', $data);
        $id = Core::getValue('id', $data);

        switch ($method) {
            case 'add':
                $cartids = $this->cartchanger($id, $cartids, 'add');
                break;
            case 'del':
                $cartids = $this->cartchanger(Core::getValue('cartid', $data), $cartids, 'del');
                break;
            case 'mod':
                $cartids = $this->cartchanger($id, $cartids, 'mod', $amount);
                break;
        }

        $cartids = $this->filterCart($cartids);
        if ($this->userid) {
            $this->updCart($cartids);
        }
        $out = [
          'comp_cart' => $this->formatCart($cartids)
        ];
//        Core::echo(__METHOD__, $id, $cartids, $out);
//        Core::echo(__METHOD__,'cartids-after-del',$cartids);
        $ctx->request()->setVar('cart', $this->getCartParam($cartids));
        $out[self::QUERY] = ['cart' => $this->getCartParam($cartids)];
        return $out;
    }

    public function getCartIds(string $cart): array
    {
        $out = Core::iterate(str_split($cart, 8), function ($v) {
            [$id, $count] = str_split($v, 5);
            return [$id, $count];
        }, true);

        return $this->filterCart($out);
    }

    private function filterCart(array $cart): array
    {
        $out = [];
        // filter out all cartids that do not exist in products
        foreach ($cart as $cartid => $count) {
            $found = $this->runSelect('products', ['isactive', 'cartid'], ['isactive' => 1, 'cartid' => $cartid], '')->fetch();
            if ($found) {
                $count = substr($count, 0, 3);
                $stock = (int)Core::value('stock', $found, 0);
                $count = max(min($count, $stock), min($stock, 1));
                $out[$cartid] = str_pad((int)$count, 3, '0', STR_PAD_LEFT);
            }
        }
        return $out;
    }

    private function getCartParam(array $cart): string
    {
        return implode('', array_map(fn($id, $count) => $id . $count, array_keys($cart), $cart));
    }


    private function cart(): array
    {
        $where = ['isactive' => 1];
        return Core::pop($this->runSelect('user.cart', array_keys($where), $where)->fetchAll());
    }

    private function updCart(array $cartids): int
    {
        $where = ['isactive' => 1, 'cartids' => $this->getCartParam($cartids)];
        $this->skipexisting = true;
        $cart = $this->cart();
        $id = Core::value('id', $cart);
        if ($id) {
            return $this->runUpdate($id, 'user.cart', array_keys($where), $where);
        } else {
            return $this->runInsert('user.cart', array_keys($where), $where);
        }
    }

    protected function modCart(string $id, bool $active = false, string $name = '', string $ordernr = ''): void
    {
        $where = ['id' => $id, 'isactive' => $active ? 1 : 0];
        if ($ordernr) {
            $where['ordernr'] = $ordernr;
        }
        if ($name) {
            $where['name'] = $name;
        } else {
            $where['name'] = null;
        }
        $this->modItem('user.cart', $where);
    }

    private function wishList(string $name): array
    {
        $where = ['isactive' => 0, 'name' => $name];
        return Core::pop($this->runSelect('user.cart', array_keys($where), $where)->fetchAll());
    }

    private function wishLists(): array
    {
        $where = ['isactive' => 0];
        return $this->runSelect('user.cart', array_keys($where), $where)->fetchAll();
    }

    /**
     * users carts in the url.path.cart
     */
    private function cartchanger(string $id, array $cartids, string $mode = 'add', int $amount = 0): array
    {
        $found = Core::pop($this->runSelect('products', ['isactive', 'id'], ['isactive' => 1, 'id' => $id])->fetchAll());
        $cartid = Core::getValue('cartid', $found);
        $stock = Core::getValue('stock', $found, 0);
        $price = (float)Core::getValue('price', $found, 0);
//        Core::echo(__METHOD__, $found);
        if ($cartid && $stock && $price > 0.1) {
            switch ($mode) {
                case 'mod':
                    $cartids[$cartid] = $amount;
                    break;
                case 'add':
                    $cartids[$cartid] = (int)Core::value($cartid, $cartids, 0) + 1;
                    break;
                case 'del':
                    $cartids = array_filter($cartids, fn($v, $k) => $k !== $id, ARRAY_FILTER_USE_BOTH);
                    break;
            }
        } else {
            $cartids = array_filter($cartids, fn($v, $k) => $k !== $id, ARRAY_FILTER_USE_BOTH);
        }

        return $cartids;
    }

    private function formatCart(array $cart, int $mwst = 10): array
    {
        $total = 0;
        if (empty($cart)) {
            return [];
        }

        $datarows = Core::iterate($cart, function ($count, $cartid) use (&$total, $mwst) {
            $out = $this->runSelect('products', ['isactive', 'cartid'], ['isactive' => 1, 'cartid' => $cartid], '')->fetch();
            $details = Core::jsonRead(Core::value('details', $out, '{}'));
            $out['info'] = Core::getValue('slug', $out, Core::value('title', $details), true);
            $price = (float)Core::getValue('price', $out, 0) * (int)$count;
            $total += ($price + ($price * $mwst / 100));
            $out['price'] = $this->fmt->formatCurrency((float)Core::getValue('price', $out, 0), Core::getValue('currency', $out));
            $out['total'] = $this->fmt->formatCurrency($price, Core::getValue('currency', $out, 'EUR', true));
            $out['amount'] = (int)$count;
            $out['cartid'] = $cartid;
            return $out;
        });

        $cart = [
          [
            'total' => $this->fmt->formatCurrency($total + ($total * $mwst / 100), 'EUR'),
            'summe' => $this->fmt->formatCurrency($total, 'EUR'),
            'mwst' => $this->fmt->formatCurrency(($total * $mwst / 100), 'EUR'),
            'cartrow' => $datarows
          ]
        ];

        return $cart;
    }

}
