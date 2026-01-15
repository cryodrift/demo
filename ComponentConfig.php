<?php

namespace cryodrift\demo;

use cryodrift\fw\Core;
use cryodrift\fw\Path;
use Exception;

class ComponentConfig
{
    const string ROLE_ADMIN = 'admin';
    const string ROLE_USER = 'user';
    const string ROLE_UNKNOWN = 'unknown';

    public function __construct(private readonly array $roles, private readonly array $routes, private readonly array $update, private array $class)
    {
    }

    public function getHandler(string $component): Component
    {
        $class = $this->getClass($component);
        return new $class($component);
    }

    public function getClass(string $component): string
    {
        return Core::value($component, $this->class);
    }

    public function hasClass(string $component): string
    {
        return $this->getClass($component) !== '';
    }

    /*
     * what component a role can see
     */
    public function roles(): array
    {
        return $this->roles;
    }

    /*
     * what component a route can see
     */
    public function routes(): array
    {
        return $this->routes;
    }

    /**
     * what component does not recieve a ui update
     * component that use scroll do update themselves or get
     * partly updated with component:refresh
     * only use this with hrefloader to prevent pagechange content refresh
     * so components do not loose there state
     */
    public function update(): array
    {
        return $this->update;
    }

    /**
     * find out if a component is visible on the current page
     * and return the params from url
     */
    public function info(Path $path, string $compname): CompInfo
    {
        $pages = Core::getValue($compname, $this->routes(), []);

        $out = new CompInfo();
        foreach ($pages as $value) {
            if (is_array($value)) {
                $parts = [];
                foreach ($value as $k => $v) {
                    $k = $k + 1;
                    $part = $path->getPart($k);
//                    Core::echo(__METHOD__,$part);
                    if ($part !== '') {
                        if (is_array($v)) {
                            $parts[$v[0]] = $part;
                        } else {
                            if ($v === $part) {
                                $parts[] = $v;
                            }
                        }
                    }
                }

                if (count($parts) && count($parts) === count($value)) {
                    $out->onpage = true;
                    $out->params = $parts;
                }
            } else {
                if ($value === $path->getPart(1) && (1 === count($path->getParts()) - 1 || $value === '')) {
                    $out->onpage = true;
                }
            }
        }
        return $out;
    }

    public function canAccess(string $component, string $role): bool
    {
        return in_array($component, Core::getValue($role, $this->roles(), []));
    }
}
