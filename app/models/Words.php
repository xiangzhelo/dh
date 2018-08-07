<?php

use Phalcon\Mvc\Model;

class Words extends Model {

    public function initialize() {
        $this->setSource('words');
    }

    public static function createOne($orign_words, $dest_words = '') {
        $model = new self();
        $model->orign_words = $orign_words;
        $model->dest_words = $dest_words;
        $model->status = empty($dest_words) ? '0' : '200';
        $model->createtime = date('Y-m-d H:i:s');
        if ($model->save()) {
            return $model;
        } else {
            return false;
        }
    }

    public static function insertSql($sql) {
        $db = \Phalcon\DI::getDefault()->getShared('db');
        $db->execute($sql);
    }

    public static function getPage($page = 1, $size = 100, $like_words = '', $status = '', $important = 0) {
        $q = [];
        $sql = '';
        if (!empty($like_words)) {
            $q['conditions'] = '(orign_words like :orign_words: or dest_words like :dest_words:)';
            $q['bind'] = [
                'orign_words' => '%' . $like_words . '%',
                'dest_words' => '%' . $like_words . '%'
            ];
            $sql = 'where (w.orign_words like "%' . $like_words . '%" or w.dest_words like "%' . $like_words . '%")';
        }
        if ($important !== '') {
            if (isset($q['conditions'])) {
                $q['conditions'].=' and important = :important:';
                $q['bind']['important'] = $important;
                $sql.=' and w.important = "' . $important . '"';
            } else {
                $q['conditions'] = 'important = :important:';
                $q['bind'] = ['important' => $important];
                $sql = 'where w.important = "' . $important . '"';
            }
        }
        if ($status !== '') {
            if (isset($q['conditions'])) {
                $q['conditions'].=' and status = :status:';
                $q['bind']['status'] = $status;
                $sql.=' and w.status = "' . $status . '"';
            } else {
                $q['conditions'] = 'status = :status:';
                $q['bind'] = ['status' => $status];
                $sql = 'where w.status = "' . $status . '"';
            }
        }
        $all_num = self::count($q);
        $sql = 'select * from words w ' . $sql . ' order by w.important asc,w.id asc limit ' . (($page - 1) * $size) . ',' . $size;
        $db = \Phalcon\DI::getDefault()->getShared('db');
        $list = $db->query($sql)->fetchAll();
//        $q['order'] = 'is_cate asc,id asc';
//        $q['limit'] = [
//            'number' => $size,
//            'offset' => (($page - 1) * $size)
//        ];
//        $modelManager = \Phalcon\DI::getDefault()->getShared('modelsManager')->createBuilder();
//        $list = $modelManager->from('Words')
//                ->where(isset($q['conditions']) ? $q['conditions'] : '', isset($q['bind']) ? $q['bind'] : [])
//                ->orderBy($q['order'])
//                ->limit($q['limit']['number'], $q['limit']['offset'])
//                ->getQuery()
//                ->execute()
//                ->toArray();
//        $list = self::find($q)->toArray();
        $pages = (object) [
                    'total_pages' => ceil($all_num / $size),
                    'last' => ceil($all_num / $size),
                    'current' => $page,
                    'all_num' => $all_num,
                    'items' => $list,
                    'before' => $page - 1,
                    'next' => $page + 1
        ];
        return $pages;
    }

    public static function updateOne($id, $dest_words) {
        if (empty($id) || empty($dest_words)) {
            return false;
        }
        $model = self::findFirst($id);
        $model->dest_words = $dest_words;
        if (!empty($dest_words)) {
            $model->status = '200';
        } else {
            $model->status = 0;
        }
        $ret = $model->update();
        if ($ret == false) {
            return false;
        } else {
            return $model;
        }
    }

}
