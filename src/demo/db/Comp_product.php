<?php

namespace cryodrift\demo\db;

use cryodrift\demo\Api;
use cryodrift\demo\ui\Select;
use cryodrift\fw\Context;
use cryodrift\fw\Core;
use cryodrift\fw\HtmlUi;
use cryodrift\fw\Main;

trait Comp_product
{
    public function comp_products(FtsProducts $fts, Context $ctx, string $search = '', array $data = [], int $products_page = 0, int $limit = 4): array
    {
        $out = [];
        $search = urldecode($search);
        $products_page = $data['products_page'] = (int)Core::getValue('products_page', $data, $products_page, true);


        $out['comp_products'] = [
          [
            'products' => $this->products_page($fts, $search, $data, $limit),
            'page' => $products_page
          ]
        ];
        $out['comp_products'][0]['genreblock'] = [[]];
        $out['comp_products'][0]['genrerow'] = $this->query(Core::fileReadOnce(__DIR__ . '/s_genre.sql'));
        if (empty($out['comp_products'][0]['genrerow'])) {
            $out['comp_products'][0]['genreblock'] = [];
        }
        $out['comp_products'][0]['search'] = $search;
        $out['comp_products'][0]['limitselector'] = $this->getLimitSelector($limit, 'products_page');
        $out['comp_products'][0]['scrollid'] = '#productslist';
        $max = $this->productsCount($fts, $limit, $search);
        $out['comp_products'][0]['maxpages'] = $max;
        $out['comp_products'][0] = array_merge($out['comp_products'][0], $this->scrollConfig('/demo/products/' . $search, $max, 'comp_products', 'products_page'));
        $out['comp_products'][0]['data-formloader-before'] = '/component.js remquery|products_page';
        $out['comp_products'][0]['data-formloader-after'] = '/component.js collect|input[name=search] replaceurl|collection|append|/demo/products/';
//        Core::echo(__METHOD__, $out);
        return $out;
    }

    private function getLimitSelector(int $limit, string $reqvar, string $varname = 'limit'): Select
    {
        $select = new Select($varname, array_combine(range(1, 100), range(1, 100)), $limit, $this->translations);
        $select->addHtmlAttribute('data-change="replacequery|'.$varname.'|self|1 remquery|' . $reqvar . ' refreshpage|/demo/api/formloader"');
        return $select;
    }

    private function productsCount(FtsProducts $fts, int $limit, string $search = ''): int
    {
        if ($search) {
            $fts->ftsConnect($this);
            $ftsids = $fts->ftsSearch('details:' . $fts->ftsEscapeLiteral($search));
            $ftsids = array_map('intval', $ftsids);
            $productscount = Core::pop($this->query('select count(id) as maxitems from products where isactive=1 and id in (' . implode(',', $ftsids) . ')'));
        } else {
            $productscount = Core::pop($this->query('select count(id) as maxitems from products where isactive=1'));
        }
//        Core::echo(__METHOD__, $search, $productscount);
        $maxfloat = Core::getValue('maxitems', $productscount, 0) / $limit;
        return (int)ceil($maxfloat) - 1;
    }

    protected function products_page(FtsProducts $fts, string $search = '', array $data = [], int $limit = 4): array
    {
        $out = [];
        $products = [];
        $search = Core::value('search', $data, $search, true);
        $maxpages = $this->productsCount($fts, $limit, $search);
        $products_page = (int)Core::getValue('products_page', $data, 0);
        if ($maxpages >= $products_page) {
            if ($search) {
                $fts->ftsConnect($this);
                $ftsids = $fts->ftsSearch('details:' . $fts->ftsEscapeLiteral($search), $products_page, $limit);
                $ftsids = array_map('intval', $ftsids);
                $found = $this->query('select * from products where isactive=1 and id in (' . implode(',', $ftsids) . ')', []);
//                Core::echo(__METHOD__, $search, $maxpages, $ftsids, $found);
            } else {
                $found = $this->runSelect('products', ['isactive'], ['isactive' => 1], '', $products_page, $limit)->fetchAll();
            }
            $products = $this->formatProducts($found);
        }


        return $products;
    }

    public function comp_product(string $id): array
    {
        $out = [];
        $data = $this->runSelect('products', ['id'], ['id' => $id])->fetchAll();

        if ($data) {
            $out['comp_product'] = [
              [
                'product' => Core::pop($this->formatProducts($data, 'tplfull'))
              ]
            ];
        } else {
            $out['comp_product'] = [
              [
                'product' => ''
              ]
            ];
        }
//        Core::echo(__METHOD__, $out);
        return $out;
    }

    protected function getProduct(string $cartid): array
    {
        $where = ['cartid' => $cartid];
        return Core::pop($this->runSelect('products', array_keys($where), $where)->fetchAll());
    }

    protected function modProduct(array $data): void
    {
        $this->runUpdate($data['id'], 'products', array_keys($data), $data);
    }

    private function formatProducts(array $data, string $tpltyp = 'tplprev'): array
    {
        $products = [];
        if ($data) {
            foreach ($data as $row) {
                $tpl = Main::path($this->tpldir . 'products/' . Core::getValue($tpltyp, $row));
                if (file_exists($tpl)) {
                    $ui = HtmlUi::fromFile($tpl);
                } else {
                    $ui = HtmlUi::fromString();
                }
                $price = (float)Core::value('price', $row, 0, true);
                $row['price'] = $this->fmt->formatCurrency($price, $row['currency']);
                $row['disabledproduct'] = ($row['stock'] === 0 || $price < 0.1) ? 'g-dh' : '';
                $row = array_merge(Core::jsonRead(Core::getValue('details', $row, '{}')), $row);
                //TODO ? cache files locally
                $row['imageurl'] = str_replace('http:', 'https:', Core::value('smallthumbnail', $row, $row['isbn'] ? '/demo/images/' . $row['isbn'] . '.png' : ''));
                $row['imageurlbig'] = str_replace('http:', 'https:', Core::value('thumbnail', $row, $row['isbn'] ? '/demo/images/' . $row['isbn'] . '.png' : ''));
                $row['author'] = Core::pop(Core::loop([$row['author']], fn($a) => $a ? 'by ' . $a : ''));

                $ui->setAttributes($row);

                $products[] = $ui;
            }
        }
        return $products;
    }

}
