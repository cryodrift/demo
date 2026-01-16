<?php

namespace cryodrift\demo\db;

use cryodrift\fw\Context;
use cryodrift\fw\Core;
use cryodrift\fw\trait\DbHelperMigrate;
use cryodrift\fw\trait\DbHelperSchema;
use cryodrift\fw\trait\DbHelperTrigger;
use Exception;

trait RepositoryBase
{
    use DbHelperMigrate;
    use DbHelperTrigger;
    use DbHelperSchema;


    protected function attachUserTable(): void
    {
        $sql = "ATTACH DATABASE '" . $this->storagedir . $this->userid . "/demo.sqlite' AS user";
//        Core::echo(__METHOD__, Colors::get('ATTACH', Colors::FG_light_blue), $sql);
        try {
            if (!is_dir($this->storagedir . $this->userid)) {
                mkdir($this->storagedir . $this->userid);
            }
            $this->pdo->exec($sql);
        } catch (\PDOException $ex) {
            // ignore alread attached database
            $msg = $ex->getMessage();
            if (!str_contains($msg, 'user is already in use')) {
                Core::echo(__METHOD__, $msg, $this->userid);
            }
        }
    }

    protected function getUserDb(string $userid): UserDb
    {
        $user = new UserDb($userid, $this->storagedir);
//        $user->getPdo()->sqliteCreateFunction('current_user_id', fn() => $this->userid);
        return $user;
    }


    /**
     *
     */
    protected function modItem(string $name, array $data, bool $skipexisting = false): string
    {
        $this->skipexisting = $skipexisting;
        $cols = Core::iterate($data, fn($v, $k) => in_array($k, $this->columns($name)) ? $k : false);
        if (empty($cols)) {
            throw new \Exception(Core::toLog('ERROR wrong data or tablename', $name, $data));
        }
        $out = '';
        if (Core::getValue('id', $data)) {
            $this->runUpdate($data['id'], $name, $cols, $data);
            $out = $data['id'];
        } else {
            unset($cols['id']);
            unset($data['id']);
            $out = $this->runInsert($name, $cols, $data);
        }
        $this->skipexisting = false;
        return $out;
    }

    protected function item(string $name, string $id, string $idcol = 'id'): array
    {
        return Core::pop($this->runSelect($name, [$idcol], [$idcol => $id])->fetchAll());
    }

    protected function items(string $name): array
    {
        return $this->runSelect($name)->fetchAll();
    }

    protected function scrollConfig(string $route, int $max, string $component, string $reqvar): array
    {
        $out = [];
        // see scroll.js scrollbarinit for params
        $dataparams = [
            // url
          '/demo/api/hrefloader',
          $component,
            // referer
          str_replace(' ', '\\\\', $route),
          $reqvar,
            //max pages
          $max
        ];
        $dataparams = implode('|', $dataparams);
        $out['data-scroll'] = 'scrollloader';
        $out['data-now-handler'] = 'scrollbarinit|' . $dataparams;
        return $out;
    }

    public function install(Context $ctx): array
    {
        $out = [];
        $this->vacuum();
        $out['tables'] = $this->migrate();
        $out['indexes'] = $this->runQueriesFromFile(__DIR__ . '/c_indexes.sql');
        $out['triggertable'] = $this->triggerTableMigrate(true);
        $out['triggertable'] .= $this->triggerTableMigrate(true, 'user.');
        $out['triggersql'] = $this->runQueriesFromFile(__DIR__ . '/c_triggers.sql', '--END;');
        $out['triggers'] = $this->triggerCreate($this->tablenames());
        $out['triggers'] .= $this->triggerCreateVersions(Core::removeKeys(['versions'], $this->tables()));
        $fts = Core::newObject(FtsProducts::class, $ctx);
        $fts->ftsConnect($this);
        $out['fts'] = $fts->ftsRecreate();
        $userdb = $this->getUserDb($this->userid);
        $out['indexes'] .= $userdb->runQueriesFromFile(__DIR__ . '/c_user_indexes.sql');
        return $out;
    }

    public function tablenames(): array
    {
        return array_keys($this->tables());
    }

    public function columns(string $tablename, array $hidecols = []): array
    {
        if (!empty($hidecols)) {
            $cols = Core::removeKeys($hidecols, $this->tables($tablename)['columns']);
        } else {
            $cols = $this->tables($tablename)['columns'];
        }
        return array_keys($cols);
    }

    public function tables(string $tablename = ''): array
    {
        $data = $this->schemaParseSqlTables(Core::fileReadOnce(__DIR__ . '/c_tables.sql'));
        return Core::removeKeys(['created', 'changed', 'deleted'], Core::getValue($tablename, $data, $data));
    }

    public function versions(string $tablename, string $id = '', string $col = '', int $page = 0, int $limit = 30): array
    {
        $where = ['table_name' => $tablename];
        if ($id) {
            $where['table_id'] = $id;
        }
        if ($col) {
            $where['column_name'] = $col;
        }
        return $this->runSelect('versions', array_keys($where), $where, 'order by created desc', $page, $limit)->fetchAll();
    }

    public function version(int $id, string $col = ''): array
    {
        $where = ['id' => $id];
        if ($col) {
            $where['column_name'] = $col;
        }
        return Core::pop($this->runSelect('versions', array_keys($where), $where)->fetchAll());
    }

    public static function createSlug(string $title): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($title)));
    }


    protected function userlang()
    {
        //TODO save and restore user lang from account
    }


    protected function fmtCurrency(array $data, string $colname): string
    {
        return $this->fmt->formatCurrency($data[$colname], Core::getValue('currency', $data, $this->currency));
    }


}
