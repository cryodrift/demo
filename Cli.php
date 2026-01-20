<?php

//declare(strict_types=1);

namespace cryodrift\demo;

use cryodrift\demo\db\FtsProducts;
use cryodrift\demo\db\Repository;
use cryodrift\fw\cli\CliUi;
use cryodrift\fw\cli\Colors;
use cryodrift\fw\cli\ParamFile;
use cryodrift\fw\cli\ParamHidden;
use cryodrift\fw\cli\ParamMulti;
use cryodrift\fw\cli\ParamSure;
use cryodrift\fw\Config;
use cryodrift\fw\Context;
use cryodrift\fw\Core;
use cryodrift\fw\interface\Handler;
use cryodrift\fw\interface\Installable;
use cryodrift\fw\Reflect;
use cryodrift\fw\trait\CliHandler;
use Exception;
use ReflectionMethod;

class Cli implements Handler, Installable
{

    use CliHandler;

    public function __construct(protected Repository $db, private readonly Config $config)
    {
    }

    public function handle(Context $ctx): Context
    {
        $ctx->response()->setStatusFinal();
        return $this->handleCli($ctx);
    }

    /**
     * @cli show all tables
     */
    protected function tables(): array
    {
        return $this->db->tables();
    }

    /**
     * @cli show all versions
     */
    protected function versions(string $tablename): array
    {
        return $this->db->versions($tablename);
    }

    /**
     * @cli read from Repository
     */
    protected function dbread(Context $ctx, string $method, array $param = [], int $page = 0, int $limit = 0): array
    {
        $methods = Reflect::getMethods($this->db);
        $out = Core::iterate($methods, function (ReflectionMethod $rm) {
            return [$rm->name, $rm];
        }, true);

        try {
            $params = Core::iterate($param, function (string $val) {
                return explode('=', $val);
            }, true);
            return $this->db->runMethod($ctx, $method, [...$params, 'page' => $page, 'limit' => $limit]);
        } catch (Exception $ex) {
            return [CliUi::arrayToCli(array_keys($out)), 'wrong method ' . $method];
        }
    }

    /**
     * @cli write data to repository
     */
    protected function dbwrite(Context $ctx, string $method, string|array $col, string|array $val, string $id = ''): array
    {
        try {
            $params = [];
            if ($id) {
                $params['id'] = $id;
            }
            if (is_array($col) && is_array($val)) {
                $params += array_combine($col, $val);
            } else {
                $params[$col] = $val;
            }
            Core::echo(__METHOD__, $col, $val, $params);
            return [Colors::get('[DONE]', Colors::FG_light_green) => $this->db->runMethod($ctx, $method, ['data' => $params])];
        } catch (Exception $ex) {
            return [Colors::get('[ERROR]', Colors::FG_light_red) => 'Wrong method ' . $method, $ex];
        }
    }


    /**
     * @cli import js array object
     */
    protected function importjs(Context $ctx, ParamFile $file, bool $write = false): array
    {
        $raw = (string)$file;
        $raw = strtr($raw, "\r\n", "  ");
        $raw = Core::pop(explode('[', $raw, 2));
        $parts = explode('},', $raw);
        $out = [];
        $lines = [];
        foreach ($parts as $part) {
            $tmp = Core::iterate(explode(', ', trim($part)), fn($k, $v) => trim(trim($k, "\n\r\t\v\0{}")));
            $tmp = Core::iterate($tmp, function ($v) {
                $parts = explode(':', $v, 2);
                return [$parts[0], trim($parts[1], "' ")];
            }, true);
            $lines[] = Core::removeKeys(['id'], $tmp);
        }
        array_pop($lines);
        Core::iterate($lines, function ($product) use (&$out, $ctx, $write) {
            $product = array_filter($product, fn($v) => !!$v);
            try {
                $product = Core::addData($product, function ($a) {
                    $a['published'] = $a['publishedYear'];
                    $a['details'] = Core::jsonWrite(Core::removeKeys(['price', 'inStock'], $a));
                    $a['cartid'] = Core::getUid(5);
                    $a['isactive'] = 1;
                    $a['tplprev'] = 'prev_book.html';
                    $a['tplfull'] = 'full_book.html';
                    $a['slug'] = $this->db::createSlug($a['title']);
                    $a['currency'] = 'EUR';

                    return $a;
                });
                $out[] = $product;
                if ($write) {
                    $this->db->runInsert('products', ['slug', 'tplprev', 'tplfull', 'isactive', 'price', 'details', 'cartid', 'currency'], $product);
                }
            } catch (Exception $ex) {
                if ($ex->getCode() === 666) {
                    Core::echo(__METHOD__, 'No Update with Same Data');
                }
            }
        });
        return $out;
    }

    /**
     * @cli load books from google (very optional)
     * @cli example q: language:de+inauthor:keyes
     * @cli example q: subject:science+fiction
     * @cli example q: inpublisher:"Perry+Rhodan+digital"
     * @cli q=intitle: Gibt Ergebnisse zurück, in denen der Text nach diesem Keyword im Titel gefunden wird.
     * @cli q=inauthor: Gibt Ergebnisse zurück, in denen der Text nach diesem Keyword im Autor gefunden wird.
     * @cli q=inpublisher: Gibt Ergebnisse zurück, bei denen der Text nach diesem Keyword im Publisher gefunden wird.
     * @cli q=subject: Gibt Ergebnisse zurück, bei denen der Text nach diesem Keyword in der Kategorieliste des Bandes aufgeführt ist.
     * @cli q=isbn: Gibt Ergebnisse zurück, bei denen der Text nach diesem Keyword die ISBN-Nummer ist.
     * @cli q=lccn: Gibt Ergebnisse zurück, bei denen der Text nach diesem Keyword die Library of Congress Control Number ist.
     * @cli q=oclc: Gibt Ergebnisse zurück, bei denen der Text nach diesem Keyword die OCLC-Nummer ist.
     */
    protected function importgbooks(Cache $cache, string $q, string $after = '', bool $full = false, int $page = 0, bool $write = false): array
    {
        $limit = 10;

        $offset = Repository::getOffset($page, $limit);
        $params = [
          'q' => $q,
          'orderBy' => 'newest',
          'projection' => $full ? 'full' : 'lite',
          'startIndex' => $offset,
          'key' => $this->config->googleapikey,
          'maxResults' => $limit
        ];
        if ($after) {
            $params['after'] = $after;
        }

        $url = 'https://www.googleapis.com/books/v1/volumes?' . http_build_query($params);
        if (!$cache->hasGroup('cli', $url)) {
            $res = Core::fileReadOnce($url);
            if (str_starts_with($res, '{')) {
                $cache->setGroup('cli', $url, $res);
            } else {
                Core::echo(__METHOD__, $res);
            }
        }
        $json = $cache->getGroup('cli', $url, '{}');
        try {
            $data = Core::jsonRead($json);
            Core::echo(__METHOD__, $json);

            $data = Core::removeKeys([
              'offers',
              'accessInfo',
              'retailPrice',
            ], $data);
            $data = Core::extractKeys($data, [
              'title',
              'subtitle',
              'pageCount',
              'textSnippet',
              'subtitle',
              'authors',
              'publishedDate',
              'description',
              'identifier',
              'categories',
              'smallThumbnail',
              'thumbnail',
              'amount',
              'currencyCode',
              'id',
            ], true);
            // collect columns into rows
            $currentkey = 0;
            $data = Core::iterate($data, function ($a) use (&$currentkey) {
                $k = array_key_first($a);
                if (array_key_exists('id', $a)) {
                    $currentkey++;
                }
                if (array_key_exists('authors', $a)) {
                    $a['authors'] = implode(',', array_values($a['authors']));
                }
                if (array_key_exists('categories', $a)) {
                    $a['categories'] = implode(',', array_values($a['categories']));
                }
//                Core::echo(__METHOD__, $k, $a);
                return [$currentkey, [strtolower($k) => $a[$k]]];
            }, true, true);

            $data = Core::iterate($data, function ($product) use ($write) {
                try {
                    $product = Core::addData($product, function ($a) {
//                        Core::echo(__METHOD__, $a);
                        $map = [
                          'author' => 'authors',
                          'genre' => 'categories',
                          'published' => 'publisheddate',
                          'googleid' => 'id',
                          'isbn' => 'identifier',
                          'price' => 'amount',
                          'currency' => 'currencycode',
                        ];

                        $a = array_merge(array_fill_keys(array_keys($map), ''), $a);

                        $a = array_merge($a, Core::extractKeys($a, $map));
//                        Core::echo(__METHOD__, $a);
                        $a['tplprev'] = 'prev_book.html';
                        $a['tplfull'] = 'full_book.html';
                        $a['slug'] = $this->db::createSlug(Core::value('title', $a));
                        $a['details'] = Core::jsonWrite(Core::removeKeys([
                          'currency',
                          'currencycode',
                          'categories',
                          'publisheddate',
                          'authors',
                          'amount',
                          'price',
                          'publishedyear',
                          ...$this->db->columns('products'),
                          $this->config->actionmethod,
                          'id',
                          'created',
                          'changed',
                          'deleted'
                        ], $a));
                        $a['cartid'] = Core::getUid(5);
                        $a['isactive'] = 1;
                        $a['stock'] = 99;

                        return $a;
                    });
                    if ($write) {
                        $old = Core::pop($this->db->runSelect('products', ['details'], $product)->fetchAll());
                        if (empty($old)) {
                            $id = $this->db->runInsert('products', ['slug', 'tplprev', 'tplfull', 'isactive', 'price', 'details', 'cartid', 'currency', 'stock'], $product);
                            Core::echo(__METHOD__, 'insert done', $id);
                        }
                    }
                    return $product;
//                    Core::echo(__METHOD__, $product);
                } catch (Exception $ex) {
                    if ($ex->getCode() === 666) {
                        Core::echo(__METHOD__, 'No Update with Same Data', $ex);
                    }
                }
            });
        } catch (Exception $ex) {
            Core::echo(__METHOD__, $ex);
        }
        return $data;
    }

    /**
     * @cli clear Cache by Groupname
     */
    protected function clearcache(Cache $cache, ParamSure $sure, string $group = 'cli'): array
    {
        if ((string)$sure) {
            $cache->clearGroup($group);
            return ['You killed it!'];
        } else {
            return ['Nothing done!'];
        }
    }

    /**
     * @cli re create fulltext search db
     * @cli TODO add tablename param
     */
    protected function recreatefts(FtsProducts $fts, ParamSure $sure): array
    {
        if ((string)$sure) {
            $fts->ftsConnect($this->db);
            $out = $fts->ftsRecreate();
            return [$out, 'You Did it!'];
        } else {
            return ['Nothing done!'];
        }
    }

    /**
     * @cli delete db
     */
    protected function deletedb(string $tablename, ParamSure $sure): array
    {
        if ((string)$sure) {
            $this->db->query('delete from ' . $tablename);
            return ['You killed it!'];
        } else {
            return ['Nothing done!'];
        }
    }

    /**
     * @cli generate/update translations
     */
    protected function trans(bool $write = false): array
    {
        $transfiles = Core::iterate(Core::dirList(__DIR__ . '/trans'), fn(\SplFileInfo $f) => [$f->getFilename(), include $f->getPathname()], true);

        $data = Core::iterate(Core::dirList(__DIR__), function (\SplFileInfo $file) use ($transfiles) {
            if ($file->getExtension() === 'html') {
                $parts = explode('{{', Core::fileReadOnce($file->getRealPath()));
                foreach ($parts as $part) {
                    $part = explode('}}', trim($part), 2);
                    $word = trim($part[0]);
                    if (preg_match('/^[A-ZÄÖÜ]/u', $word)) {
                        foreach ($transfiles as $lang => $translations) {
                            yield [$lang, [$word => Core::getValue($word, $translations, $word, true)]];
                        }
                    }
                }
            }
        }, true, true);

        foreach ($data as $lang => $arr) {
            $data[$lang] = array_merge($arr, $transfiles[$lang]);
            ksort($data[$lang]);
        }
        if ($write) {
            foreach ($data as $lang => $translations) {
                $code = '<?php' . "\nreturn " . var_export($translations, true) . ";";
                Core::fileWrite(__DIR__ . '/trans/' . $lang, $code);
            }
        }
        return $data;
    }

    /**
     * @cli test dbhelper database
     * @cli param: -id=1
     */
    protected function testdb(string $id = '', string $val = ''): array
    {
        $out = [];


        $data = $this->db->book($id);
        $out['originaldata'] = $data;
        $data = [];
        if ($id) {
            $data['id'] = $id;
        }

        $data['title'] = $val;
        $data['author'] = 'TEXT';
        $data['subtitle'] = 'TEXT';
        $data['description'] = 'TEXT';
        $data['isbn13'] = 'TEXT';
        $data['published_at'] = 'NUMERIC';
        $data['price_cents'] = 'INTEGER';
        $data['currency'] = 'TEXT';
        $data['stock'] = 'INTEGER';
        $data['cart'] = 'INTEGER';
        $out['modifieddata'] = $data;
//        $this->db->skipexisting = true;
        try {
            $out['update'] = $this->db->modBook($data);
        } catch (\Exception $ex) {
            // send error to browser
            if ($ex->getCode() !== 666) {
                Core::echo(__METHOD__, $ex->getMessage());
            }
        }

        $out['books'] = $this->db->books(0, 10);
        return $out;
    }

    /**
     * @cli delete doubles from table
     */
    protected function deletedoubles(string $table, bool $write = false, array $hidecol = [], array $addcol = []): array
    {
        $t2 = $table;
//        $t2 = Core::pop(explode('.', $table));
        $sql = 'delete from ' . $table . ' where id not in (SELECT min(id) as id FROM ' . $t2 . ' GROUP BY ';
        $sql .= implode(',', [...$addcol, ...$this->db->columns($table, $hidecol)]) . ')';
        Core::echo(__METHOD__, $sql);
        if ($write) {
            return $this->db->query($sql);
        }
        return [$sql];
    }

    /**
     * @cli check database problems
     */
    protected function checktable(string $table): array
    {
        [$db, $tablename] = $this->db->splitTablename($table);
        $queries = [
          'PRAGMA ' . $db . 'table_info(' . $tablename . ')',
          'select * from ' . $db . $tablename . '',
          ['select * from ' . $db . $tablename . ' where id=:id', ['id' => '1']],
          ['delete from ' . $table . ' where id=:id', ['id' => '1']],
          "SELECT name, sql FROM " . $db . "sqlite_master WHERE type = 'trigger' AND tbl_name = '" . $tablename . "'"
        ];
//        Core::echo(__METHOD__, $queries);
        $out = [];
        foreach ($queries as $sql) {
            try {
                if (is_array($sql)) {
                    $out[] = $this->db->query($sql[0], $sql[1]);
                } else {
                    $out[] = $this->db->query($sql);
                }
            } catch (Exception $ex) {
                Core::echo(__METHOD__, $ex->getMessage(), $sql);
            }
        }

        return $out;
    }

    /**
     * @cli save file(s) to db
     * @cli params: -path="" (dir or file)
     * @cli params: [-skip] (skip if exists in db)
     * @cli params: [-pattern=""] (filter pattern "*.jpg|*.gif")
     */
    protected function importimages(Context $ctx, string $path, ?ParamMulti $pattern = null, bool $skip = false, string $root = ''): string
    {
        if ($path) {
            if (is_dir($path)) {
                try {
                    $this->db->transaction();
                    $filter = function (\SplFileInfo $file) use ($pattern, $root): bool {
                        if ($file->isFile() && $pattern) {
                            foreach ($pattern as $pat) {
                                if (str_starts_with($pat, '-')) {
                                    if (fnmatch(trim($pat, '-'), $file->getFilename(), FNM_CASEFOLD)) {
                                        return false;
                                    } else {
                                        return true;
                                    }
                                } elseif (fnmatch($pat, $file->getFilename(), FNM_CASEFOLD)) {
                                    return true;
                                }
                            }
                            return false;
                        } else {
                            if ($root) {
                                return false;
                            } else {
                                return true;
                            }
                        }
                    };
                    $files = Core::dirList($path, $filter);

                    CliUi::withProgressBar($files, function (\SplFileInfo $file) use ($skip, $path, $ctx) {
                        if (!$file->isDir()) {
                            $trydisk = 10;
                            $fobj = null;
                            do {
                                $fobj = new \SplFileObject($file->getPathname());
                                try {
                                    $pathname = strtr($file->getPathname(), '\\', '/');
                                    $root = strtr($path, '\\', '/');
                                    $data = $this->db->runMethod($ctx, 'item', ['name' => 'images', 'id' => $pathname, 'idcol' => 'src']);
                                    unset($data['uid']);
                                    $this->db->runMethod($ctx, 'modImage', ['path' => $pathname, 'data' => $data, 'skip' => $skip, 'root' => $root]);
                                } catch (\Exception $ex) {
                                    Core::echo(__METHOD__, $file->getPathname(), $ex);
                                    $msg = $ex->getMessage();
                                    if ($trydisk < 0 || str_contains($msg, 'Permission denied')) {
                                        return;
                                    } else {
                                        // if disk is in sleepmode wait for it
                                        $trydisk--;
                                        usleep(250);
                                    }
                                }
                            } while (!$fobj);
                        }
                    });
                    $this->db->commit();
                } catch (\Exception $hl) {
                    $this->db->rollback();
                    Core::echo(__METHOD__, $hl);
                }
            } elseif (is_file($path)) {
                if (!$root) {
                    return Core::toLog('Missing Param -root');
                }
                try {
                    $this->db->transaction();
                    $path = strtr($path, '\\', '/');
                    $root = strtr($root, '\\', '/');
                    $data = $this->db->runMethod($ctx, 'item', ['name' => 'images', 'id' => $path, 'idcol' => 'src']);
                    unset($data['uid']);
                    $this->db->runMethod($ctx, 'modImage', ['path' => $path, 'data' => $data, 'skip' => $skip, 'root' => $root]);
                } catch (\Exception $hl) {
                    $this->db->rollback();
                    Core::echo(__METHOD__, $hl);
                }
            } else {
                return '';
            }

//            $this->db->ftsRecreate();
            return Core::toLog('Done');
        }
        return '';
    }

    /**
     * @cli convert flat cache to dirbased
     */
    protected function convertcache(Cache $cache): array
    {
        return Core::iterate(Core::dirList($cache->getDir(),fn(\SplFileInfo $f)=>!$f->isDir()), function (\SplFileInfo $file) use ($cache) {
                $dest = $cache->getDir() . $cache->key2dir($file->getFilename());
//                return $dest;
                Core::dirCreate($dest);
                copy($file->getPathname(), $dest);
                if (file_exists($dest)) {
                    unlink($file->getPathname());
                }
        });
    }

    /**
     * @cli This will be called by cryodrift\fw\tools\Cli , route /sys modules
     *
     */
    public function install(Context $ctx): array
    {
        // Demo accounts:
        //
        //  Admin:    admin@cryodrift.lan / admin123
        //
        //  Customer: customer@example.com / password123

        $this->usercreate($ctx, 'admin@cryodrift.lan', new ParamHidden($ctx, 'password', 'admin123'), ComponentConfig::ROLE_ADMIN);
        $this->usercreate($ctx, 'customer@example.com', new ParamHidden($ctx, 'password', 'password123'), ComponentConfig::ROLE_USER);

        return $this->db->install($ctx);
    }

    /**
     * @cli User create
     */
    protected function usercreate(Context $ctx, string $name, ?ParamHidden $pass, string $role = ComponentConfig::ROLE_UNKNOWN): bool
    {
        try {
            $userctx = clone $ctx;
            $user = Core::newObject(\cryodrift\user\Cli::class, $userctx);
            $userctx->request()->setParam('sessionuser', $name);
            $user->register($userctx, $name, $pass);
            $db = Core::newObject(Repository::class, $userctx);
            $db->install($ctx);
            //Kontoinformationen adden
            $db->comp_account_edit($userctx, ['name' => $name, 'email' => $name, 'role' => $role]);
        } catch (Exception $ex) {
            Core::echo(__METHOD__, $ex);
            return false;
        }

        return true;
    }

}
