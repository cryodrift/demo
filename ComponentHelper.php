<?php

namespace cryodrift\demo;

use cryodrift\fw\cli\Colors;
use cryodrift\fw\Context;
use cryodrift\fw\Core;
use cryodrift\fw\HtmlUi;
use cryodrift\fw\Main;
use cryodrift\fw\Path;
use Exception;

trait ComponentHelper
{

    protected function handlePage(Context $ctx): Context
    {
        $ui = HtmlUi::fromFile($this->config->templatepath);
        $ui->setAttributes(['langcode' => $ctx->language()]);
        $ui->setAttributes(['title' => $this->config->title]);
        $ui->setAttributes(['description' => $this->config->description]);
        $path = $ctx->request()->path();
        [$vis, $comps] = $this->calculateComponents($ctx, $path, []);

        foreach ($vis as $component => $value) {
            $this->outHelperAttributes([$component . '_vis' => $value ? '' : 'g-dh']);
        }

        $ui->setAttributes($comps, false, false);

        $this->outHelperAttributes($this->translations->translation());
        $ui->setAttributes(['formtranslation' => "\n setTranslations(" . Core::jsonWrite($this->translations->getFormErrors()) . ');'], false, false);

        return $this->outHelper($ui, $ctx);
    }

    protected function updateComponent(Context $ctx, string $compname, string $url): array
    {
        $output = [];
        try {
            if ($this->compconfig->canAccess($compname, Core::getValue('role', $this->db->account()))) {
                // this is to get data from url parts ,settup in component config
                $path = Path::fromUrl($url);
                $info = $this->compconfig->info($path, $compname);

                if ($this->compconfig->hasClass($compname)) {
                    $comp = $this->compconfig->getHandler($compname);
                    $params = Core::getParams($comp, 'update', [...$info->params, ...$ctx->request()->vars()], $ctx, false);
                    $output = $comp->update(...$params);
                } elseif (method_exists($this->db, $compname)) {
                    $params = Core::getParams($this->db, $compname, [...$info->params, ...$ctx->request()->vars()], $ctx, false);
                    $output = $this->db->$compname(...$params);
                }
            }
        } catch (Exception $ex) {
            if ($ex->getCode() == 666 || !$ctx->hasUser()) {
                throw $ex;
            }
            Core::echo(__METHOD__, $ex);
        }
        return $output;
    }


    protected function calculateComponents(Context $ctx, Path $path, array $rendered = []): array
    {
        $out = [];
        $vis = [];
        // we iterate because we need to deactivate hide the other components (and its not that slow)
        foreach ($this->compconfig->routes() as $component => $pages) {
            $out[$component] = '';
            $vis[$component] = false;
            try {
                $info = $this->compconfig->info($path, $component);
//                Core::echo(__METHOD__, $component, $ctx->user(false), $path->getString(), 'ispage:' . $info['ispage'], 'canSee:' . $this->canAccess($component));
                if ($info->onpage && $this->compconfig->canAccess($component, Core::getValue('role', $this->db->account()))) {
                    $data = $this->renderComponent($ctx, $component, $info->params, Core::getValue($component, $rendered, []));
                    if (!empty($data->getAttributes()[$component])) {
                        $vis[$component] = true;
                        $data->setAttributes($this->translations->translation());
                    }
                    $out[$component] = $data;
                }
            } catch (\Exception $ex) {
                Core::echo(__METHOD__, Colors::get('[Missing Component]', Colors::FG_red), $ex->getMessage(), $ex->getTraceAsString(), $ex->getCode());
            }
        }

        return [$vis, $out];
    }

    private function renderComponent(Context $ctx, string $component, array $urlparams = [], array $output = [], string $template = ''): HtmlUi
    {
        $template = $template ?: $component;
        $source = [...$urlparams, ...Core::removeKeys([$this->config->actionmethod], $ctx->request()->vars())];
        if ($this->compconfig->hasClass($component)) {
            $comp = $this->compconfig->getHandler($component);
            $params = Core::getParams($comp, 'render', [...$source, 'data' => $output], $ctx);
            $ui = $comp->render(...$params);
        } else {
            $ui = HtmlUi::fromString(Core::fileReadOnce(Main::path($this->tpldir . $template . '.html')), $template);
            if (empty($output)) {
                if (method_exists($this->db, $component)) {
                    $params = Core::getParams($this->db, $component, $source, $ctx);
                    $ui->setAttributes($this->db->$component(...$params), true);
                } else {
                    $ui->setAttributes([$component => [[]]]);
                }
            } else {
                $ui->setAttributes($output, true);
            }
        }
        $ui->setAttributes(['actionmethod' => $this->config->actionmethod]);
        return $ui;
    }


}
