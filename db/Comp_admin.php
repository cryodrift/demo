<?php

namespace cryodrift\demo\db;


use cryodrift\demo\Api;
use cryodrift\demo\ComponentConfig;
use cryodrift\demo\ui\Checkbox;
use cryodrift\demo\ui\Form;
use cryodrift\demo\ui\Select;
use cryodrift\fw\Config;
use cryodrift\fw\Context;
use cryodrift\fw\Core;
use cryodrift\fw\HtmlUi;
use cryodrift\fw\Main;
use SplFileInfo;

trait Comp_admin
{
    use RepositoryBase;
    use Comp_order;

    public function cont_admin(): array
    {
        $out = [];
        $out['cont_admin'] = [['dashboard' => [[]]]];
        return $out;
    }

    public function comp_admin_order(Context $ctx, string $ordernr, array $data = []): array
    {
        $out = [];
        //        Core::echo(__METHOD__, $ordernr,$data);
        switch (Core::getValue($this->config->actionmethod, $data)) {
            case'MOD':
                $order = $this->getOrder($ordernr, true);
                if ($order) {
                    $this->transaction();
                    $this->modItem('orderitems', $data);
                    $items = $this->orderItems(Core::value('id', $order));
                    $found = Core::iterate($items, fn($v) => $v['status'] !== self::STATUS_FINISHED || null);
                    if (empty($found)) {
                        $order['status'] = self::STATUS_FINISHED;
                    } else {
                        $order['status'] = self::STATUS_PROCESSING;
                    }
                    $this->modOrder($order, true);
                    $this->commit();
                }
//                Core::echo(__METHOD__, $userid, $item);
                break;
            default:
        }
        $ordered = $this->getOrder($ordernr, true);
        $order = $this->formatOrdered($ordered);

//        Core::echo(__METHOD__, $ordernr, $order);

        $order['items'] = Core::iterate($order['items'], function (array $data, int|string $key) use ($order) {
//            Core::echo(__METHOD__, $data);
            $data['orderstatus'] = new Form("/demo/api/formloader/comp_admin_order");
            $select = new Select('status', self::ORDER_STATUS, Core::value('status', $data), $this->translations, 'g-dh');
            $select->addHtmlAttribute('data-change="hide show|prev submit stop"');
            $data['orderstatus']->addInputHidden('orderid', $order['id']);
            $data['orderstatus']->addInputHidden('ordernr', $order['ordernr']);
            $data['orderstatus']->addInputHidden('id', $data['id']);
            $data['orderstatus']->addInputHidden($this->config->actionmethod, 'MOD');
            $span = HtmlUi::fromString('<span class="g-status-{{status}}" data-click="hide show|next stop">{{status}}</span>');
            $span->setAttributes(['status' => $data['status']]);
            $data['orderstatus']->addHtmlUi($span);
            $data['orderstatus']->addHtmlUi($select);

            foreach (['ispaid', 'isdelivered', 'isreturned', 'isrefunded'] as $name) {
                $form = new Form('/demo/api/formloader/comp_admin_order');
                $form->addHtmlAttribute('data-change="submit"');
                $form->addInputHidden('orderid', $order['id']);
                $form->addInputHidden('ordernr', $order['ordernr']);
                $form->addInputHidden('id', $data['id']);
                $form->addInputHidden($this->config->actionmethod, 'MOD');
                $form->addHtmlUi(new Checkbox($name, (bool)Core::value($name, $data)));
                $data[$name] = $form;
            }
            return [$key, $data];
        }, true);
        $order['username'] = $this->getUserDb($order['user'])->getEmail();

        $out['comp_admin_order'] = [$order];
        $id = Core::value('id', $order);
        $refresh = HtmlUi::fromFile('/demo/ui/comp_admin_orders_page.html')->fromBlock('row')->setAttributes($order);
        $refresh->setAttributes($this->translations->translation());
        $out[self::REFRESH] = [['id' => 'order_' . $id, 'html' => trim((string)$refresh)]];
//        Core::echo(__METHOD__, $out);
        return $out;
    }

    public function comp_admin_orders(array $data = [], int $orders_page = 0, string $search = '', int $admin_limit = 20): array
    {
        $out = [];
        $orders_page = $data['orders_page'] = (int)Core::getValue('orders_page', $data, $orders_page, true);
        $out['comp_admin_orders'] = [];
        $out['comp_admin_orders'][0]['limitselector'] = $this->getLimitSelector($admin_limit, 'orders_page', 'admin_limit');
        $out['comp_admin_orders'][0]['scrollid'] = '#orderslist';
        $max = $this->adminOrdersCount($admin_limit, $search);
        if ($max >= $orders_page) {
            $page = $this->comp_admin_orders_page($data, $admin_limit, $search)['comp_admin_orders_page'];
            $out['comp_admin_orders'][0] = array_merge($out['comp_admin_orders'][0], $this->scrollConfig('/demo/admin/orders/' . $search, $max, 'comp_admin_orders', 'orders_page'), ...$page);
        } else {
            throw new \Exception('No Orders Page found');
        }

        return $out;
    }

    protected function comp_admin_orders_page(array $data = [], int $admin_limit = 10, string $search = ''): array
    {
        $out = [];
        $orders_page = (int)Core::getValue('orders_page', $data, 0, true);
        $out['comp_admin_orders_page'] = [[]];

        $orders = $this->runSelect('orders', [], [], '', $orders_page, $admin_limit)->fetchAll();
        $orders = Core::iterate($orders, function ($order) {
            $order['username'] = $this->getUserDb($order['user'])->getEmail();
            return $order;
        });
        if ($orders) {
            $out['comp_admin_orders_page'] = [
              [
                'row' => $orders,
                'page' => $orders_page,
                'maxpages' => $this->adminOrdersCount($admin_limit, $search)
              ],
            ];
//        Core::echo(__METHOD__, $orders_page, $data);
            // the only page where this is allowed, no products no rendering
            $out[self::ROUTE] = '/demo/comp_admin_orders_page';
        }
        return $out;
    }

    private function adminOrdersCount(int $admin_limit, string $search = ''): int
    {
        //TODO search
        $count = Core::pop($this->query('select count(id) as maxitems from orders'));
        $maxfloat = Core::getValue('maxitems', $count, 0) / $admin_limit;
        return (int)ceil($maxfloat) - 1;
    }


    public function comp_admin_users(): array
    {
        $out = [];

        $dirs = Core::dirList($this->storagedir, fn(SplFileInfo $p, $d) => $p->isDir() && $d < 1);
        $users = Core::iterate($dirs, function (SplFileInfo $d) {
            $id = $d->getFilename();
            $db = $this->getUserDb($id);
            if ($db->getEmail()) {
                return [
                  'id' => $id,
                  'name' => $db->getName(),
                  'email' => $db->getEmail(),
                  'role' => $db->getRole(),
                ];
            }
        });
        $out['comp_admin_users'] = [
          [
            'row' => $users
          ]
        ];

        return $out;
    }

    public function comp_admin_user(ComponentConfig $ccfg, string $id, array $data = []): array
    {
        $out = [];
        $out['comp_admin_user'] = [];

        if ($id && file_exists($this->storagedir . $id)) {
            $user = $this->getUserDb($id);
            switch (Core::getValue($this->config->actionmethod, $data)) {
                case'VER':
//                $data = $this->version($version);
//                $where = ['details' => $data['old_data']];
//                $this->runUpdate($id, 'products', array_keys($where), $where);
                    break;
                case'MOD':
                    $user->save($data);
                    break;
                default:
            }
            $user = $this->getUserDb($id);
            $roles = array_combine(array_keys($ccfg->roles()), array_keys($ccfg->roles()));
            $select = new Select('role', $roles, $user->getRole(), $this->translations);

            $out['comp_admin_user'] = [
              [
                'roles' => $select,
                'actionmethod' => $this->config->actionmethod,
                'name' => $user->getName(),
                'email' => $user->getEmail()
              ]
            ];
        }
        return $out;
    }

    public function comp_admin_products(FtsProducts $fts, array $data = [], int $products_page = 0, string $search = '', int $admin_limit = 10): array
    {
        $out = [];
        $search = Core::value('search', $data, $search, true);
        $products_page = $data['products_page'] = (int)Core::getValue('products_page', $data, $products_page, true);
        $out['comp_admin_products'] = [];
        $out['comp_admin_products'][0]['limitselector'] = $this->getLimitSelector($admin_limit, 'products_page', 'admin_limit');
        $out['comp_admin_products'][0]['scrollid'] = '#productslist';
        $out['comp_admin_products'][0]['search'] = $search;
        $out['comp_admin_products'][0]['data-formloader-before'] = '/component.js remquery|products_page';
        $out['comp_admin_products'][0]['data-formloader-after'] = '/component.js collect|input[name=search] replaceurl|collection|append|/demo/admin/products/';

        $max = $this->adminProductsCount($fts, $admin_limit, $search);
//        Core::echo(__METHOD__, $max, $products_page, $search);
        if ($max >= $products_page) {
            $comp_admin_products_page = $this->comp_admin_products_page($fts, $data, $admin_limit, $search)['comp_admin_products_page'];
            $out['comp_admin_products'][0] = array_merge($out['comp_admin_products'][0], $this->scrollConfig('/demo/admin/products/' . $search, $max, 'comp_admin_products', 'products_page'), ...$comp_admin_products_page);
        }
        return $out;
    }

    /**
     * @web sends parsed content to be replaced in dom where getElementById(domid)
     * domid format it tablename_id
     */
    protected function productrefresh(string $id): HtmlUi
    {
        $out = [];
        $data = $this->runSelect('products', ['id'], ['id' => $id])->fetch();
        return HtmlUi::fromFile('/demo/ui/comp_admin_products_page.html')->fromBlock('row')->setAttributes($data)->setAttributes($this->translations->translation());
    }

    public function comp_admin_products_page(FtsProducts $fts, array $data = [], int $admin_limit = 10, string $search = ''): array
    {
        $out = [];
        $search = Core::value('search', $data, $search, true);
        $products_page = (int)Core::getValue('products_page', $data, 0, true);
        $out['comp_admin_products_page'] = [[]];
        if ($search) {
            $products = $this->query('select * from products where id in (' . implode(',', $this->searchProducts($fts, $search, $products_page, $admin_limit)) . ')', []);
        } else {
            $products = $this->runSelect('products', [], [], '', $products_page, $admin_limit)->fetchAll();
        }

        if ($products) {
            $out['comp_admin_products_page'] = [
              [
                'row' => $products,
                'page' => $products_page,
                'maxpages' => $this->adminProductsCount($fts, $admin_limit, $search)
              ],
            ];
//        Core::echo(__METHOD__, $products_page, $data);
            // the only page where this is allowed, no products no rendering
            $out[self::ROUTE] = '/demo/comp_admin_products_page';
        }
        return $out;
    }

    private function searchProducts(FtsProducts $fts, string $search, int $page = 0, int $admin_limit = 0): array
    {
        $fts->ftsConnect($this);
        $ftssearch = $fts->ftsEscapeLiteral($search);
        $searchquery = [
          'details:' . $ftssearch,
          'slug:' . $ftssearch,
          'created:' . $ftssearch,
          'cartid:' . $ftssearch,
          'price:' . $ftssearch
        ];
        $ftsids = $fts->ftsSearch(implode(' OR ', $searchquery), $page, $admin_limit);
        $ftsids = array_map('intval', $ftsids);
        return $ftsids;
    }

    private function adminProductsCount(FtsProducts $fts, int $admin_limit, string $search = ''): int
    {
        if ($search) {
            $productscount = Core::pop($this->query('select count(id) as maxitems from products where id in (' . implode(',', $this->searchProducts($fts, $search)) . ')'));
        } else {
            $productscount = Core::pop($this->query('select count(id) as maxitems from products'));
        }
        $maxfloat = Core::getValue('maxitems', $productscount, 0) / $admin_limit;
        return (int)ceil($maxfloat) - 1;
    }

    public function comp_admin_product(FtsProducts $fts, Context $ctx, int $id = 0, int $version = 0, array $data = []): array
    {
        $out = [];
        switch (Core::getValue($this->config->actionmethod, $data)) {
            case'VER':
                $data = $this->version($version, 'details');
                $where = ['details' => $data['old_data']];
                $this->runUpdate($id, 'products', array_keys($where), $where);
                $fts->ftsConnect($this);
                $fts->ftsSave([...$where, 'id' => $id]);
                break;
            case'MOD':
                if (!empty($_FILES)) {
                    Core::echo(__METHOD__, $_FILES);
                    // TODO save uploaded file in ui/images
//                    $data = array_merge(Core::iterate($_FILES['data']['name'], function ($v, $k) {
//                        Core::echo(__METHOD__, $v, $k);
//
//                    }), $data);
                    $data = array_merge($_FILES['data']['name'], $data);
                }

                $data['details'] = Core::jsonWrite(Core::removeKeys([...$this->columns('products'), $this->config->actionmethod, 'id', 'created', 'changed', 'deleted'], $data));
                $data['slug'] = $this::createSlug($data['slug']);
//                Core::echo(__METHOD__, $data);

                $cols = $this->columns('products', ['isgroup', 'cartid', 'checkout', 'sold']);

                if ($id) {
                    $this->runUpdate($id, 'products', $cols, $data);
                } else {
                    unset($cols['id']);
                    unset($data['id']);
                    $id = $this->runInsert('products', $cols, $data);
                }
                $data = Core::extractKeys($data, $cols);
                $fts->ftsConnect($this);
                $fts->skipexisting = true;
                $fts->ftsSave([...$data, 'id' => $id]);
                break;
            default:
        }
        if ($id) {
            $data = $this->runSelect('products', ['id'], ['id' => $id])->fetch();
        } else {
            $data = [];
        }
        $templates = Core::iterate(Core::dirList(Main::path($this->tpldir . '/products')), fn(SplFileInfo $file) => $file->getBasename());
        $data['tplprev'] = new Select('tplprev', $templates, Core::getValue('tplprev', $data, '', true), $this->translations, '');
        $data['tplfull'] = new Select('tplfull', $templates, Core::getValue('tplfull', $data, '', true), $this->translations, '');
        $data['currency'] = new Select('currency', ['EUR', 'USD'], Core::getValue('currency', $data, '', true), $this->translations, '');
        $data['isactive'] = Core::getValue('isactive', $data, '', true) ? 'checked' : '';
        $data['details'] = Core::catch(fn() => $this->renderInputs(Core::jsonRead(Core::value('details', $data, '[]', true))));
        if ($id) {
            $versions = $this->versions('products', $id, 'details', 0, 5);
            $versions = Core::iterate($versions, function ($version) {
                if ($version['old_data']) {
                    $version['old_data'] = substr($version['old_data'], 0, 10);
                }
                if ($version['new_data']) {
                    $version['new_data'] = substr($version['new_data'], 0, 10);
                }

                if ($version['operation'] === 'UPDATE') {
                    return ['name' => implode(', ', Core::extractKeys($version, ['column_name', 'created', 'old_data', 'new_data'])), 'value' => $version['id']];
                }
            });
            $data['versions'] = new Select('version', $versions, $version, $this->translations, '');
            $data['versions']->addHtmlAttribute('data-change="submit"');
        } else {
            $data['versions'] = '---';
        }

        $out['comp_admin_product'] = [$data];
        if ($id) {
            $out[self::REFRESH] = [['id' => 'product_' . $id, 'html' => trim((string)$this->productrefresh($id))]];
        }


        return $out;
    }

    public function comp_admin_images(array $data = [], string $search = '', int $images_page = 0, int $admin_limit = 10): array
    {
        $images_page = Core::value('images_page', $data, $images_page, true);
        $search = Core::value('search', $data, $search);
        $superselect = '';

        $rows = $this->getImagePage($search, $images_page, $admin_limit);
        $max = $this->getImagePageCount($search, $admin_limit);

        // initial request
        if (Core::value('images_page', $data) === '') {
            $superselect = new Form('/demo/admin/images');
            $superselect->addInputHidden($this->config->actionmethod, 'DIR');
            $superselect->addInputHidden('images_page', '0');
            $cont = [];
            Core::iterate($this->query('select distinct reldir from images'), function ($row) use (&$cont) {
                $reldir = Core::value('reldir', $row);
                if ($reldir) {
                    $cont[] = $reldir;
                    $cont[] = dirname($reldir);
                }
            });
            $options = array_unique($cont);
            $select = new Select('search', $options, $search, $this->translations);
            $select->addHtmlAttribute('data-change="replacequery|search|self|1 remquery|images_page refreshpage|/demo/api/formloader"');
            $select->addHtmlAttribute('data-click="searchselect|hide"');
            $superselect->addHtmlUi($select);
        }
        $searchform = [
          'data-formloader-before' => '/component.js remquery|images_page',
          'data-formloader-after' => '/component.js collect|input[name=search] replacequery|search|collection|1',
          'search' => $search
        ];
//        $search = '';
        $out = [
          'comp_admin_images' => [
            [
              'superselect' => $superselect,
              'searchform' => [$searchform],
              'scrollid' => '#imageslist',
              'maxpages' => $max,
              'limitselector' => $this->getLimitSelector($admin_limit, 'images_page', 'admin_limit'),
              ...$this->scrollConfig('/demo/admin/images/', $max, 'comp_admin_images', 'images_page'),
              'page' => $images_page,
              'row' => $rows

            ]
          ]
        ];
        return $out;
    }

    public function comp_admin_image(int $id): array
    {
        $where = ['id' => $id];

        $img = Core::pop($this->runSelect('images', array_keys($where), $where)->fetchAll());
//        Core::echo(__METHOD__, $where, $img);
        $img['alt'] = $img['src'];
        $img['src'] = urlencode($img['src']);
//        Core::echo(__METHOD__, $where, $img);
        return [
          'comp_admin_image' => [
            $img
          ]
        ];
    }

    protected function getImagePage(string $search, int $page, int $admin_limit): array
    {
        if ($search) {
            $rows = $this->query('select * from images where instr(lower(src), lower(:search)) > 0 ', ['search' => $search], $page, $admin_limit);
        } else {
            $rows = $this->runSelect('images', [], [], '', $page, $admin_limit)->fetchAll();
        }
        $rows = Core::iterate($rows, function ($r) {
            $r['src'] = urlencode($r['src']);
            return $r;
        });

        return $rows;
    }

    protected function getImagePageCount(string $search, int $admin_limit): int
    {
        if ($search) {
            $maxquery = $this->query('select count(id) as maxitems from images where instr(lower(src), lower(:search)) > 0 ', ['search' => $search]);
            $items = (int)Core::value('maxitems', Core::pop($maxquery));
        } else {
            $items = (int)Core::value('maxitems', Core::pop($this->query('select count(id) as maxitems from images')));
        }
        $max = round($items / $admin_limit) - 1;
        return $max;
    }

    protected function modImage(string $path, array $data = [], bool $skip = false, string $root = ''): string
    {
        $data['src'] = Core::value('src', $data, $path, true);
        $data['filedate'] = filectime($path);
        $data['reldir'] = str_replace($root, '', dirname($path));
        $data['slug'] = Core::value('slug', $data, self::createSlug(str_replace($root, '', $path)), true);
//        $data['uid'] = Core::value('uid', $data, fn() => md5_file($path), true);
        return $this->modItem('images', $data, $skip);
    }

    public function renderInputs(array $data): HtmlUi
    {
        $out = [];
        // iterate data an create input for each
        foreach ($data as $key => $value) {
            $out[] = HtmlUi::fromFile('/demo/ui/detailsinput.html')->setAttributes(['key' => $key, 'value' => $value]);
        }

        return HtmlUi::fromString()->setAttributes($out);
    }

}
