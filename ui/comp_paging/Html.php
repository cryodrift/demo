<?php

namespace cryodrift\demo\ui\comp_paging;

use cryodrift\fw\Context;
use cryodrift\fw\Core;
use cryodrift\fw\HtmlUi;
use cryodrift\fw\trait\TranslationHelper;

/**
 * Generic Paging component extracted from demo/ui/paging2 to be reusable as comp_paging
 */
class Html extends HtmlUi
{
    use TranslationHelper;

    private int $currentPage = 1;
    private int $totalItems = 0;

    public function __construct(
        private Context $ctx,
        private int $itemsPerPage = 10,
        private int $maxPageLinks = 10,
        private array $pageSizeOptions = [10, 25, 50, 100],
        private string $varname = 'page',
        private string $pageSizeVarname = 'pagesize',
        private bool $showPageSizeSelector = true,
        private bool $showFirstLastButtons = true,
        private bool $showPageIndicator = true,
        private bool $showTotalItems = true,
        private string $cssClass = 'pagination',
        private string $activeClass = 'active',
        private string $disabledClass = 'disabled',
        private string $itemClass = 'page-item',
        private string $linkClass = 'page-link',
    ) {
        parent::__construct();
        // Reuse the same markup as paging2 to keep behavior intact
        $this->lazyFile(__DIR__ . '/paging2.html');
        $this->currentPage = (int)$ctx->request()->vars($this->varname, $this->currentPage);

        $requestedPageSize = (int)$ctx->request()->vars($this->pageSizeVarname, 0);
        if ($requestedPageSize > 0 && in_array($requestedPageSize, $this->pageSizeOptions)) {
            $this->itemsPerPage = $requestedPageSize;
        }
    }

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    protected function prepareVars(): void
    {
        $this->currentPage = (int)$this->ctx->request()->vars($this->varname, $this->currentPage);

        $totalPages = $this->getTotalPages();
        if ($this->currentPage < 1) {
            $this->currentPage = 1;
        } elseif ($this->currentPage > $totalPages) {
            $this->currentPage = $totalPages;
        }

        $pos = (int)(floor(($this->currentPage - 1) / $this->maxPageLinks) * $this->maxPageLinks) + 1;

        $links = Core::loop($this->maxPageLinks, function ($a, $k) use ($pos) {
            $k += $pos;
            if ($k <= $this->getTotalPages()) {
                return [
                    'active' => $this->currentPage === $k ? $this->activeClass : '',
                    'url' => $this->buildUrl([$this->varname => $k]),
                    'page' => $k,
                ];
            } else {
                return null;
            }
        });

        if ($this->getTotalPages() > $this->maxPageLinks && $pos + $this->maxPageLinks < $this->getTotalPages()) {
            $links[] = [
                'active' => '',
                'url' => '#',
                'page' => '...'
            ];
        }

        $pageSizeOptions = [];
        if ($this->showPageSizeSelector) {
            foreach ($this->pageSizeOptions as $size) {
                $pageSizeOptions[] = [
                    'value' => $size,
                    'url' => $this->buildUrl([$this->pageSizeVarname => $size]),
                    'selected' => $this->itemsPerPage === (int)$size ? 'selected' : '',
                    'label' => $size
                ];
            }
        }

        $skipBackwardItems = max(0, $this->currentPage - $this->maxPageLinks);
        $skipnext = $this->currentPage + $this->maxPageLinks;
        $skipForwardItems = $skipnext < $this->getTotalPages() ? $skipnext : 0;

        $vars = [
            'previous_active' => $this->currentPage <= 1 ? $this->disabledClass : '',
            'next_active' => $this->currentPage >= $this->getTotalPages() ? $this->disabledClass : '',
            'first_active' => $skipBackwardItems <= 0 ? $this->disabledClass : '',
            'last_active' => $skipForwardItems <= 0 ? $this->disabledClass : '',
            'prev_page_url' => $this->buildUrl([$this->varname => max(1, $this->currentPage - 1)]),
            'next_page_url' => $this->buildUrl([$this->varname => min($this->getTotalPages(), $this->currentPage + 1)]),
            'skip_backward_page' => $skipBackwardItems,
            'skip_forward_page' => $skipForwardItems,
            'skip_backward_url' => $skipBackwardItems > 0 ? $this->buildUrl([$this->varname => $skipBackwardItems, $this->pageSizeVarname => $this->itemsPerPage]) : '#',
            'skip_forward_url' => $skipForwardItems > 0 ? $this->buildUrl([$this->varname => $skipForwardItems, $this->pageSizeVarname => $this->itemsPerPage]) : '#',
            'pageSizeVarname' => $this->pageSizeVarname,
            'items_per_skip' => $this->maxPageLinks,
            'varname' => $this->varname,
            'links' => $links,
            'current_page' => $this->currentPage,
            'total_pages' => $this->getTotalPages(),
            'total_items' => $this->totalItems,
            'itemsPerPage' => $this->itemsPerPage,
            'css_class' => $this->cssClass,
            'item_class' => $this->itemClass,
            'link_class' => $this->linkClass,
        ];
        $vars['page_size_selector'] = [[]];
        $vars['page_indicator'] = [[]];
        $vars['total_items_display'] = [[]];
        $vars['first_last_buttons'] = [[]];
        $vars['last_button'] = [[]];

        if ($this->showPageSizeSelector && !empty($pageSizeOptions)) {
            $vars['page_size_selector'] = [[
                'page_size_options' => $pageSizeOptions
            ]];
        }

        if ($this->showPageIndicator) {
            $vars['page_indicator'] = [['_render' => true]];
        }

        if ($this->showTotalItems) {
            $vars['total_items_display'] = [['_render' => true]];
        }

        if ($this->showFirstLastButtons) {
            $vars['first_last_buttons'] = [['_render' => true]];
            $vars['last_button'] = [['_render' => true]];
        }

        // pass attributes to HtmlUi for rendering
        $this->setAttributes($vars, true);
    }

    public function __toString(): string
    {
        // ensure attributes are prepared before base render
        $this->prepareVars();
        return parent::__toString();
    }

    private function buildUrl(array $params): string
    {
        $queryParams = $this->ctx->request()->getParams();

        foreach ($params as $key => $value) {
            $queryParams[$key] = $value;
        }

        $query = [];
        foreach ($queryParams as $key => $value) {
            $query[] = urlencode($key) . '=' . urlencode($value);
        }

        return '?' . implode('&', $query);
    }

    public function setVarname(string $varname): self
    {
        $this->varname = $varname;
        $this->currentPage = (int)$this->ctx->request()->vars($this->varname, $this->currentPage);
        return $this;
    }

    public function setPageSizeVarname(string $varname): self
    {
        $this->pageSizeVarname = $varname;
        return $this;
    }

    public function setPageSizeOptions(array $options): self
    {
        $this->pageSizeOptions = $options;
        return $this;
    }

    public function showPageSizeSelector(bool $show = true): self
    {
        $this->showPageSizeSelector = $show;
        return $this;
    }

    public function showFirstLastButtons(bool $show = true): self
    {
        $this->showFirstLastButtons = $show;
        return $this;
    }

    public function showPageIndicator(bool $show = true): self
    {
        $this->showPageIndicator = $show;
        return $this;
    }

    public function showTotalItems(bool $show = true): self
    {
        $this->showTotalItems = $show;
        return $this;
    }

    public function setCssClasses(
        string $paginationClass = 'pagination',
        string $itemClass = 'page-item',
        string $linkClass = 'page-link',
        string $activeClass = 'active',
        string $disabledClass = 'disabled'
    ): self {
        $this->cssClass = $paginationClass;
        $this->itemClass = $itemClass;
        $this->linkClass = $linkClass;
        $this->activeClass = $activeClass;
        $this->disabledClass = $disabledClass;
        return $this;
    }

    public function setTotalItems(int $total): self
    {
        $this->totalItems = $total;
        return $this;
    }

    public function setItemsPerPage(int $perPage): self
    {
        $this->itemsPerPage = $perPage;
        return $this;
    }

    public function getLimit(): int
    {
        return $this->itemsPerPage;
    }

    private function getTotalPages(): int
    {
        return $this->totalItems > 0 ? ceil($this->totalItems / $this->itemsPerPage) : 1;
    }

}
