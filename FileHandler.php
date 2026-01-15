<?php

namespace cryodrift\demo;

use cryodrift\fw\Context;
use cryodrift\fw\Core;
use cryodrift\fw\FakeFileInfo;
use cryodrift\fw\Main;
use cryodrift\fw\Response;
use cryodrift\fw\tool\image\Thumb;

class FileHandler extends \cryodrift\fw\FileHandler
{

    public function external(Context $ctx, Cache $cache): Context
    {
        $route = $ctx->request()->route();
        $thumb = $ctx->request()->vars('t');
//        Core::echo(__METHOD__,'route', $route,'pathstr',$ctx->request()->path()->getString());
        $pathname = str_replace($route . '/', '', $ctx->request()->path()->getString());
        $pathname = urldecode($pathname);
//        Core::echo(__METHOD__, $pathname);
        $res = $this->getFileResponse($pathname, $cache, '', (bool)$thumb, true);
        $ctx->response($res);
        return $ctx;
    }

    public function getFileResponse(string $pathname, Cache $cache, string $filename = '', bool $thumb = false, bool $inline = false): Response
    {
        $res = new Response('');
        $bin = null;
        switch ($thumb) {
            case true:
                try {
                    if (file_exists($pathname)) {
                        $key = $cache->key2dir(md5($pathname));
                        if (!$cache->has($key)) {
                            $bin = $this->getThumb(new \SplFileObject($pathname), $this->config->thumbsize);
                            if ($bin) {
                                $cache->set($key, $bin);
                            }
                        }
                        if ($cache->has($key)) {
                            $bin = $cache->get($key);
                        }
                    }

                    break;
                } catch (\RuntimeException $ex) {
                    Core::echo(__METHOD__, $ex);
                }
                break;
            default:
        }

        if (!$bin && file_exists($pathname)) {
//                    Core::echo(__METHOD__, $pathname);
            $bin = file_get_contents($pathname);
        }

        if ($bin) {
            $file = new FakeFileInfo(-1);
            $file->fwrite($bin);
            $fext = Core::pop(explode('.', $pathname));
            $types = \cryodrift\fw\FileHandler::mimetypes();
            $type = Core::getValue($fext, $types);
            $file->setFextension($fext);
            $headers = FileHandler::getHeaders($file, $this->config->cacheDuration);
            $res->setHeaders([...$headers]);
            if (!$inline) {
                $h = FileHandler::getDownloadHeader($type, $filename);
                $res->setHeaders([...$res->getHeaders(), ...$h]);
            }
            $res->setContent($bin);
        }
        return $res;
    }

    private function getThumb(\SplFileInfo $file, int $width = 1000): string
    {
        if (!in_array(strtolower($file->getExtension()), ['jpg', 'jpeg', 'bmp'])) {
            return '';
        }
        $pathname = $file->getPathname();
        $fsize = $file->getSize();
        $thumb = Core::catch(fn() => exif_thumbnail($pathname), false);
//        Core::echo(__METHOD__, strlen($thumb), $fsize);
        if (!$thumb && $fsize >= $this->config->maxthumbsize) {
            $thumb = $this->createThumb($file, $width);
        }
        return $thumb;
    }

    private function createThumb(\SplFileInfo $file, int $w): string
    {
        try {
            $t = new Thumb($file);
            $t->setWidth($w);
            return $t->generateThumb($file->getPathname());
        } catch (\Exception $ex) {
            if ($ex->getCode() == 100) {
//                Core::echo(__METHOD__, 'bigger?!', $file->getPathname());
                return file_get_contents($file->getPathname());
            } else {
                Core::echo(__METHOD__, $ex);
                return '';
            }
        }
    }

}
