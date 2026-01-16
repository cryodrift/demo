<?php

use cryodrift\fw\Core;
use cryodrift\demo\ui\comp_paging\Html;

if (!isset($ctx)) {
    $ctx = Core::newContext(new \cryodrift\fw\Config());
}

$cfg = $ctx->config();

$cft[Html::class] = [
  'itemsPerPage' => Core::env('COMP_PAGING_ITEMSPERPAGE', 10),
  'maxPageLinks' => Core::env('COMP_PAGING_MAXPAGELINKS', 10),
  'showFirstLastButtons' => Core::env('COMP_PAGING_SHOWFIRSTLAST', true),
  'showPageIndicator' => Core::env('COMP_PAGING_SHOWPAGEINDICATOR', true),
  'showTotalItems' => Core::env('COMP_PAGING_SHOWTOTALITEMS', true),
  'showPageSizeSelector' => Core::env('COMP_PAGING_SHOWPAGESIZESELECTOR', true),
  'pageSizeOptions' => explode(',', Core::env('COMP_PAGING_PAGESIZEOPTIONS', '10,15,20,25,30,35,40,45,50,75,100,500,1000')),
  'cssClass' => Core::env('COMP_PAGING_CSSCLASS', 'pagination'),
  'itemClass' => Core::env('COMP_PAGING_ITEMCLASS', 'page-item'),
  'linkClass' => Core::env('COMP_PAGING_LINKCLASS', 'page-link'),
  'activeClass' => Core::env('COMP_PAGING_ACTIVECLASS', 'active'),
  'disabledClass' => Core::env('COMP_PAGING_DISABLEDCLASS', 'disabled'),
  'pageSizeVarname' => Core::env('COMP_PAGING_PAGESIZEVARNAME', 'pagesize'),
];
