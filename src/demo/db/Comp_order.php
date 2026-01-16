<?php

namespace cryodrift\demo\db;

use cryodrift\demo\Api;
use cryodrift\fw\Context;
use cryodrift\fw\Core;

trait Comp_order
{
    use RepositoryBase;
    use Comp_cart;

    public function comp_order(string $ordernr): array
    {
        $out = [];
        $ordered = $this->comp_ordered($ordernr);
        if ($ordered) {
            $out['username'] = $this->getUserDb($ordered['comp_ordered'][0]['user'])->getEmail();
            $out['comp_order'] = $ordered['comp_ordered'];
        } else {
            $out['comp_order'] = [];
        }

        return $out;
    }

    public function comp_orders(): array
    {
        $out = [];
        // show orders
        $orders = $this->orders($this->userid);
        $orders = Core::addData($orders, function ($data) {
            $data['total'] = $this->fmtCurrency($data, 'total');
            return $data;
        });

        $out['ordersrow'] = $orders;
        $out['comp_orders'] = [[]];

        return $out;
    }

    public function comp_ordered(string $ordernr): array
    {
        $out = [];
        $ordered = $this->getOrder($ordernr);
        $ordered = $this->formatOrdered($ordered);
        $out['comp_ordered'] = [$ordered];

//        Core::echo(__METHOD__, $out);
        return $out;
    }

    public function comp_placeorder(Context $ctx, array $data = []): array
    {
        $out = [];
        try {
            if (Core::getValue($this->config->actionmethod, $data) === 'buy') {
                $this->transaction();
                $cart = $this->getCartIds(Core::value('cartids', $this->cart(), []));


                if (empty($cart)) {
                    throw new \Exception('Cart is empty');
                }


                $order = [];
                $order['ordernr'] = $ordernr = Core::getUid(8);
                $orderid = $this->modItem('orders', $order);

                $orderitems = Core::iterate($cart, function (int $amount, string $cartid) use ($orderid) {
                    if ($amount === 0) {
                        throw new \Exception('One Item in your Cart has no Stock!');
                    }
                    $product = $this->getProduct($cartid);
                    $product['stock'] = $product['stock'] - $amount;
                    $product['checkout'] = $product['checkout'] + $amount;
                    $this->modProduct($product);

                    $orderitem = Core::extractKeys($product, [
                      'cartid',
                      'slug',
                      'details',
                      'tplprev',
                      'tplfull',
                      'price',
                      'currency',
                    ]);
                    $cols = $this->columns('user.address');
                    $adr = $this->address(self::ADR_TYP_DELIVER);
                    $adrparts = Core::extractKeys($adr, $cols);
                    $adrparts = Core::removeKeys(['id', 'selected', 'type'], $adrparts);
                    $orderitem['shipaddress'] = implode(',', array_values($adrparts));
                    $orderitem['shipprice'] = '';
                    $orderitem['shipdetail'] = '';
                    $orderitem['orderid'] = $orderid;
                    $orderitem['product_id'] = $product['id'];
                    $orderitem['amount'] = $amount;
                    $orderitem['status'] = self::STATUS_PROCESSING;
//                    Core::echo(__METHOD__, 'adr', $orderitem);

                    $this->modItem('orderitems', $orderitem);
                    return $orderitem;
                });

                $order['total'] = array_sum(array_map(fn($i) => $i['price'] * $i['amount'], $orderitems));
                $order['user'] = $this->userid;
                $order['productcount'] = count($orderitems);
                $order['quantity'] = array_sum(array_column($orderitems, 'amount'));
                $order['status'] = self::STATUS_PROCESSING;
//                Core::echo(__METHOD__, $orderitems, $order);
                $id = $this->modItem('orders', $order);
                $this->modCart(Core::value('id', $this->cart()), false, '', $ordernr);
                $this->commit();

                $out[self::ROUTE] = '/demo/checkout/finished/' . $ordernr;
                $ctx->request()->setVar('cart', '');
                $out[self::QUERY] = ['cart' => ''];
            }
        } catch (\Exception $ex) {
            Core::echo(__METHOD__, $ex->getMessage(), $ex->getTraceAsString());
        }
        return $out;
    }


    protected function getOrder(string $ordernr, bool $admin = false): array
    {
        $where = ['ordernr' => $ordernr, 'user' => $this->userid];
        if ($admin) {
            $where = ['ordernr' => $ordernr];
        }

        $out = Core::pop($this->runSelect('orders', array_keys($where), $where)->fetchAll());
        if (empty($out)) {
            throw new \Exception('No Order found for' . $ordernr);
        }
        return $out;
    }

    protected function order(string $id): array
    {
        return Core::pop($this->runSelect('orders', ['id'], ['id' => $id])->fetchAll());
    }

    protected function orders(string $user): array
    {
        return $this->runSelect('orders', ['user'], ['user' => $user])->fetchAll();
    }

    protected function orderItem(string $id, string $orderid): array
    {
        return Core::pop($this->runSelect('orderitems', ['id', 'orderid'], ['id' => $id, 'orderid' => $orderid])->fetchAll());
    }

    protected function orderItems(string $orderid): array
    {
        return $this->runSelect('orderitems', ['orderid'], ['orderid' => $orderid])->fetchAll();
    }

    protected function modOrder(array $data, bool $skipexisting = false): string
    {
        return $this->modItem('orders', $data, $skipexisting);
    }

    private function formatOrdered(array $data, int $mwst = 10): array
    {
        //        Core::echo(__METHOD__, $ordered);
        $ordered = Core::addData($data, function ($item) {
            $item['total'] = $this->fmtCurrency($item, 'total');
            $item['shipcost'] = $this->fmtCurrency($item, 'shipcost');
            return $item;
        });

        $items = $this->orderItems($ordered['id']);
        $items = Core::iterate($items, function ($v) {
            return [$v['cartid'], $v];
        }, true);


        $items = Core::iterate($items, function ($item) use ($mwst) {
            $amount = (int)$item['amount'];
            $currency = Core::getValue('currency', $item, 'EUR', true);
            $details = Core::jsonRead(Core::value('details', $item, '{}'));
            $item['info'] = Core::getValue('slug', $item, Core::value('title', $details), true);
            $price = (float)Core::getValue('price', $item, 0) * $amount;
            $total = ($price + ($price * $mwst / 100));
            $item['price'] = $this->fmt->formatCurrency(Core::getValue('price', $item, 0), $currency);
            $item['total'] = $this->fmt->formatCurrency($total, $currency);
            $item['pricesum'] = $this->fmt->formatCurrency($price, $currency);
            return $item;
        });
        $ordered['items'] = $items;
        return $ordered;
    }

}
