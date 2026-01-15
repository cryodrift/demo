<?php

//declare(strict_types=1);

namespace cryodrift\demo;

use cryodrift\demo\db\Repository;
use cryodrift\fw\Config;
use cryodrift\fw\Context;
use cryodrift\fw\Core;
use cryodrift\fw\HtmlUi;
use cryodrift\fw\interface\Cachegroup;
use cryodrift\fw\interface\Handler;
use cryodrift\fw\Main;
use cryodrift\fw\Path;
use cryodrift\fw\trait\WebHandler;
use Exception;

/**
 * This is for Data Storage
 */
class Api implements Handler
{
    use WebHandler;
    use ComponentHelper;


    public function __construct(
      private readonly Repository $db,
      private readonly Config $config,
      private readonly ComponentConfig $compconfig,
      private readonly Translations $translations,
      private readonly string $tpldir,
      private readonly ?Cachegroup $cache,
    ) {
    }

    public function handle(Context $ctx): Context
    {
        $ctx->setLanguage($this->translations->getLanguages(), $this->config->defaultlang);
        $this->translations->setLang($ctx->language());

        try {
            if (!Config::isCli() && !$ctx->request()->isPost()) {
                throw new \Exception('Only POST requests are allowed');
            }
            $this->methodname = 'command';
            $ctx = $this->handleWeb($ctx);
            $data = $ctx->response()->getData();
            $ctx->response()->setData($data);
        } catch (\Throwable $ex) {
            Core::echo(__METHOD__, '[ERROR]', $ex);
            $ctx->response()->setData([
              'errors' => [
                  //TODO replace words
                'error_main' => str_replace($this->translations->keys(), $this->translations->values(), $ex->getMessage())
              ]
            ]);
        }
        return $ctx;
    }

    /**
     * @web get components by href, see config
     * to prevent save at call we need to use onetime ids,
     * but we need requestvars
     */
    protected function hrefloader(Context $ctx, string $route): array
    {
        $path = Path::fromUrl($route);
        $ctx = clone $ctx;
        $ctx->request()->setPath($path);
        [$vis, $out] = $this->calculateComponents($ctx, $path, []);
        $query = [];
        array_walk($out, function (HtmlUi|string &$v) use (&$query) {
            if ($v instanceof HtmlUi) {
                $tmp = Core::getValue($this->db::QUERY, $v->getAttributes(), []);
                if (!empty($tmp)) {
                    $query = array_merge($tmp, $query);
                }
                $v = (string)$v;
            }
        });

        return [
          'route' => $route,
          'visible' => $vis,
          'components' => $out,
            // prevent update on page change
          'update' => $this->compconfig->update(),
          'errors' => ['error_main' => ''],
          'query' => $query
        ];
    }


    /**
     * @web get components by href, see config
     * this saves data to one component
     */
    protected function formloader(Context $ctx, string $route, string $component = ''): array
    {
        // save data to component even when its not visible on the page

        if ($component) {
            $output = $this->updateComponent($ctx, $component, $route);
        } else {
            $output = [];
        }

        $newroute = Core::getValue($this->db::ROUTE, $output, $route);
        $query = Core::getValue($this->db::QUERY, $output, []);
        $path = Path::fromUrl($newroute);
//        Core::echo(__METHOD__,'vars1',$ctx->request()->vars());
        $ctx = clone $ctx;
        $ctx->request()->setPath($path);
//        Core::echo(__METHOD__,'vars2',$ctx->request()->vars());
        [$vis, $out] = $this->calculateComponents($ctx, $path, [$component => $output]);
        array_walk($out, fn(&$v) => $v = (string)$v);
        $out['error_main'] = '';
        $refresh = Core::getValue($this->db::REFRESH, $output, []);
//        array_walk($refresh, fn(&$v) => $v = (string)$v);
        return [
          'component' => $component,
          'refresh' => $refresh,
          'route' => $newroute,
          'visible' => $vis,
          'components' => $out,
            // always update when form post
          'update' => [],
          'errors' => ['error_main' => ''],
          'query' => $query
        ];
    }

}
