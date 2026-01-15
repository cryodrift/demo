<?php

//declare(strict_types=1);

namespace cryodrift\demo\db;

use cryodrift\demo\Api;
use cryodrift\demo\Translations;
use cryodrift\fw\Config;
use cryodrift\fw\Context;
use cryodrift\fw\Core;
use cryodrift\fw\Path;
use cryodrift\fw\trait\DbHelper;
use Exception;
use NumberFormatter;
use Locale;

class Repository implements \cryodrift\fw\interface\DbHelper
{
    use DbHelper;
    use RepositoryBase;
    use Comp_account;
    use Comp_order;
    use Comp_cart;
    use Comp_admin;
    use Comp_product;
    use Comp_checkout;

    const string ROUTE = '_route_';
    const string QUERY = '_query_';
    const string REFRESH = '_refresh_';

    const string CART_TYP_CART = 'cart';
    const string CART_TYP_WISH = 'wish';
    const string ADR_TYP_INVOICE = 'invoice';
    const string ADR_TYP_DELIVER = 'deliver';


    const string STATUS_FINISHED = 'finished';
    const string STATUS_PROCESSING = 'processing';


    const array CART_TYP = [
      'cart',
      'wish'
    ];

    const array ORDER_STATUS = [
      self::STATUS_PROCESSING,
      self::STATUS_FINISHED,
    ];


    protected string $userid;
    protected readonly string $userrole;
    protected NumberFormatter $fmt;
    protected string $username;
    protected string $userlang;


    public function __construct(
      protected readonly Context $ctx,
      private readonly string $storagedir,
      protected string $currency,
      protected readonly Translations $translations,
      protected readonly array $menus,
      protected readonly string $tpldir,
      protected readonly string $dbname,
      protected readonly Config $config,
      string $defaultlang,
    ) {
        $this->connect('sqlite:' . $storagedir . $this->dbname . '.sqlite');
        $this->userlang = $ctx->setLanguage($this->translations->getLanguages(), $defaultlang);
        $this->translations->setLang($this->userlang);

        try {
            $this->userid = $ctx->user();
            $this->username = $ctx->user(false);
            $this->attachUserTable();
            $this->userrole = Core::getValue('role', $this->account(), 'unknown');
        } catch (Exception $ex) {
            $this->userid = '';
            $this->username = '';
            $this->userrole = 'unknown';
            Core::echo(__METHOD__, $ex->getMessage());
        }
        setlocale(LC_ALL, '');
        Locale::setDefault(locale_get_default());
        $this->fmt = new NumberFormatter(Locale::getDefault(), NumberFormatter::CURRENCY);
    }

    public function getDbname(): string
    {
        return $this->dbname;
    }


    protected function changeUser(string $userid = ''): void
    {
        if ($userid) {
            $sql = "DETACH DATABASE user";
            $this->query($sql);
            $this->userid = $userid;
            $this->attachUserTable();
            $this->username = Core::getValue('name', $this->account(), []);
        } else {
            $sql = "DETACH DATABASE user";
            $this->query($sql);
            $this->userid = $this->ctx->user();
            $this->username = $this->ctx->user(false);
            $this->attachUserTable();
        }
    }

    public function cont_home(Context $ctx): array
    {
        $products = $this->products_page(...Core::getParams($this, 'products_page', ['limit' => 3], $ctx));
        $out = [
          'cont_home' => [
            ['products' => $products]
          ],
        ];
        return $out;
    }

    public function comp_header(Context $ctx): array
    {
        $query = ['lang' => $this->userlang];
        $translations = $this->translations->translations();
        $data = ['formtranslation' => "\n " . Core::jsonWrite($this->translations->getFormErrors()) . ''];

        if ($this->userid) {
            $data['btnlogout'] = [[]];
            $data['btnlogin'] = [];
        } else {
            $data['btnlogout'] = [];
            $data['btnlogin'] = [[]];
        }

        return [
          'menulinks' => $this->getMenu($ctx->request()->path(), $translations),
          'langcode' => $this->userlang,
          'languageswitch' => [['select' => $this->translations->getOptions()]],
          'comp_header' => [$data],
          self::QUERY => $query
        ];
    }

    public function comp_login(): array
    {
        $out = [];
        $out[self::ROUTE] = '/demo/login';
        $out['comp_login'] = [[]];

        return $out;
    }

    protected function getMenu(Path $page, array $translations): array
    {
        $out = [];

        foreach (Core::getValue($this->userrole, $this->menus, []) as $key => $value) {
            $out[] = [
              'link' => $key,
              'name' => Core::getValue($value, $translations, $value),
              'selected' => $key === $page->getPart(1) ? 'g-active' : '',
            ];
        }

        return $out;
    }

    public function runMethod(Context $ctx, string $name, array $params = []): array|string
    {
        if (Config::isCli() && method_exists($this, $name)) {
            return $this->$name(...Core::getParams($this, $name, $params, $ctx));
        }
        throw new Exception('Not possible');
    }

}
