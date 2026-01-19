<?php

//declare(strict_types=1);

/**
 * @env USER_STORAGEDIRS=".cryodrift/users/"
 * @env DEMO_CACHEDIR=".cryodrift/cache/demo/"
 * @env DEMO_GOOGLEAPIKEY=""
 */

use cryodrift\demo\comp\config\Api;
use cryodrift\demo\ComponentConfig;
use cryodrift\fw\Core;

if (!isset($ctx)) {
    $ctx = Core::newContext(new \cryodrift\fw\Config());
}

$cfg = $ctx->config();

if (Core::env('USER_USEAUTH')) {
    \cryodrift\user\Auth::addConfigs($ctx, [
      'demo/images',
    ]);
    // when we have a session we use it, but we dont need to be logged in to show the route
    \cryodrift\user\Auth::addConfigs($ctx, [
      'demo',
    ], 'usesession');
}

$cfg[\cryodrift\demo\Cache::class] = ['cachedir' => Core::env('DEMO_CACHEDIR')];
$cfg[\cryodrift\demo\Cli::class] = \cryodrift\demo\Api::class;
$cfg[\cryodrift\demo\Web::class] = \cryodrift\demo\Api::class;

$cfg[\cryodrift\demo\Api::class] = [
  'templatepath' => __DIR__ . '/ui/base/main.html',
  'storagedir' => Core::env('USER_STORAGEDIRS'),
  'googleapikey' => Core::env('DEMO_GOOGLEAPIKEY'),
  'dbname' => 'demo',
  'title' => 'Shop Demo',
  'cache' => \cryodrift\demo\FakeCache::class,
  'description' => 'Shop demo built with cryodrift',
  'defaultlang' => 'de',
  'currency' => 'EUR',
  'tpldir' => 'demo/ui/',
  'actionmethod' => '_method',
  'menus' => [
    'admin' => [
      '' => 'Home',
      'products' => 'Products',
      'admin' => 'Admin',
      'cart' => 'Cart',
      'account' => 'Account',
    ],
    'user' => [
      '' => 'Home',
      'products' => 'Products',
      'about' => 'About',
      'contact' => 'Contact',
      'cart' => 'Cart',
      'account' => 'Account',
    ],
    'unknown' => [
      '' => 'Home',
      'products' => 'Products',
      'about' => 'About',
      'contact' => 'Contact',
      'cart' => 'Cart'
    ]
  ],
];

$cfg[\cryodrift\demo\ComponentConfig::class] = [
  'class' => [
    'comp_admin_config' => Api::class
  ],
  'roles' => [
    ComponentConfig::ROLE_ADMIN => [
      'comp_header',
      'cont_home',
      'cont_admin',
      'cont_checkout',
      'cont_cart',
      'comp_admin_dashboard',
      'comp_admin_order',
      'comp_admin_orders',
      'comp_admin_orders_page',
      'comp_admin_user',
      'comp_admin_users',
      'comp_admin_user',
      'comp_admin_products',
      'comp_admin_products_page',
      'comp_admin_product',
      'comp_admin_images',
      'comp_admin_image',
      'comp_order',
      'comp_orders',
      'comp_ordered',
      'comp_account',
      'comp_account_edit',
      'comp_address',
      'comp_checkout',
      'comp_placeorder',
      'comp_cart',
      'comp_product',
      'comp_products',
    ],
    ComponentConfig::ROLE_USER => [
      'comp_header',
      'cont_home',
      'cont_checkout',
      'cont_cart',
      'comp_products',
      'comp_product',
      'comp_about',
      'comp_order',
      'comp_orders',
      'comp_ordered',
      'comp_contact',
      'comp_account',
      'comp_account_edit',
      'comp_address',
      'comp_placeorder',
      'comp_cart',
    ],
    ComponentConfig::ROLE_UNKNOWN => [
      'comp_header',
      'cont_home',
      'cont_checkout',
      'cont_cart',
      'comp_cart',
      'comp_about',
      'comp_contact',
      'comp_products',
      'comp_product',
      'comp_login',
    ]
  ],
  'routes' => [
    'cont_home' => ['', 'home'],
    'cont_checkout' => ['checkout'],
    'cont_cart' => ['cart'],
    'cont_admin' => [
      'admin',
      ['admin', 'orders'],
      ['admin', 'order', ['orderid']],
      ['admin', 'users'],
      ['admin', 'user', ['id']],
      ['admin', 'products'],
      ['admin', 'product', ['id']],
      ['admin', 'images'],
      ['admin', 'image', ['id']],
    ],
    'comp_admin_dashboard' => ['admin'],
    'comp_admin_orders' => [['admin', 'orders']],
    'comp_admin_order' => [['admin', 'order', ['ordernr']]],
    'comp_admin_users' => [['admin', 'users']],
    'comp_admin_user' => [['admin', 'user', ['id']]],
    'comp_admin_products' => [['admin', 'products'], ['admin', 'products', ['search']]],
    'comp_admin_product' => [['admin', 'product', ['id']]],
    'comp_admin_images' => [['admin', 'images']],
    'comp_admin_image' => [['admin', 'image', ['id']]],
    'comp_about' => ['about'],
    'comp_order' => [['account', 'order', ['ordernr']]],
    'comp_orders' => [['account', 'orders']],
    'comp_ordered' => [['checkout', 'finished', ['ordernr']]],
    'comp_contact' => ['contact'],
    'comp_account' => ['account'],
    'comp_account_edit' => [['account', 'settings']],
    'comp_product' => [['product', ['id']]],
    'comp_products' => ['products', ['products', ['search']]],
    'comp_address' => ['checkout', 'account'],
    'comp_cart' => ['checkout', 'cart'],
    'comp_formtest' => ['test'],
    'comp_login' => ['login', 'logout', 'checkout']
  ],
  'update' => [
    'comp_admin_products' => false,
    'comp_products' => false
  ]
];

// we want the header on each route
$cfg[\cryodrift\demo\ComponentConfig::class]['routes']['comp_header'] = array_merge(...array_values(Core::removeKeys([], $cfg[\cryodrift\demo\ComponentConfig::class]['routes'])));

$cfg[\cryodrift\demo\db\Repository::class] = [
  ...$cfg[\cryodrift\demo\Api::class],
  ...['storagedir' => Core::env('USER_STORAGEDIRS')],
];
$cfg[\cryodrift\demo\db\FtsProducts::class] = [
  'storagedir' => Core::env('USER_STORAGEDIRS'),
];

$translations = Core::iterate(Core::dirList(__DIR__ . '/trans'), fn(\SplFileInfo $f) => [str_replace('.php', '', $f->getFilename()), include $f->getPathname()], true);

$cfg[\cryodrift\demo\FileHandler::class] = [
  'cacheDuration' => 3333333,
  'maxthumbsize' => 2000,
  'thumbsize' => 1500
];
$cfg[\cryodrift\demo\Translations::class] = [
  'translations' => $translations,
  'formerrors' => [
    'valueMissing',
    'badInput',
    'patternMismatch',
    'tooShort',
    'tooLong',
    'rangeUnderflow',
    'rangeOverflow',
    'stepMismatch',
    'typeMismatch',
    'text_valueMissing',
    'search_valueMissing',
    'tel_valueMissing',
    'password_valueMissing',
    'email_valueMissing',
    'email_typeMismatch',
    'url_valueMissing',
    'url_typeMismatch',
    'number_valueMissing',
    'date_valueMissing',
    'datetime-',
    'month_valueMissing',
    'week_valueMissing',
    'time_valueMissing',
    'color_valueMissing',
    'range_valueMissing',
    'checkbox_valueMissing',
    'radio_valueMissing',
    'file_valueMissing',
    'select_valueMissing',
    'select-one_valueMissing',
    'select-multiple_valueMissing',
    'textarea_valueMissing',
  ]
];

\cryodrift\fw\Router::addConfigs($ctx, [
  'demo/cli' => \cryodrift\demo\Cli::class
], \cryodrift\fw\Router::TYP_CLI);

\cryodrift\fw\Router::addConfigs($ctx, [
  'demo' => \cryodrift\demo\Web::class,
  'demo/api' => \cryodrift\demo\Api::class,
  'demo/images' => [[\cryodrift\demo\FileHandler::class, 'external']],
  'infinityscroll' => [[\cryodrift\demo\FileHandler::class, 'folder',['assetdir'=>'demo/ui/infinityscroll']]],
  'demo/js' => [[\cryodrift\demo\FileHandler::class, 'folder',['assetdir'=>'demo/ui/js']]],
], \cryodrift\fw\Router::TYP_WEB);

\cryodrift\fw\FileHandler::addConfigs($ctx, [
  'component.js' => 'demo/ui/component.js',
  'scroll.js' => 'demo/ui/scroll.js'
]);
