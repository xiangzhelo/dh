<?php

use Phalcon\Mvc\Model;

class Categories extends Model {

    public function initialize() {
        $this->setSource('categories');
    }

    public static function getPage($page = 1, $size = 100, $like_words = '', $status = '') {
        $q = [];
        if ($status !== '') {
            if (isset($q['conditions'])) {
                $q['conditions'].=' and status = :status:';
                $q['bind']['status'] = $status;
            } else {
                $q['conditions'] = 'status = :status:';
                $q['bind'] = ['status' => $status];
            }
        }
        $all_num = self::count($q);
        $q['order'] = 'id desc';
        $q['limit'] = [
            'number' => $size,
            'offset' => (($page - 1) * $size)
        ];
        $list = self::find($q)->toArray();
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

    public static function updateOne($id, $orign_words, $dest_words = '') {
        if (!empty($id) || !empty($orign_words)) {
            return false;
        }
        $model = self::findFirst($id);
        $model->orign_words = $orign_words;
        $model->dest_words = $dest_words;
        if (empty($dest_words)) {
            $model->status = 200;
        } else {
            $model->status = 0;
        }
        $ret = $model->save();
        if ($ret == false) {
            return false;
        } else {
            return $model;
        }
    }

}
