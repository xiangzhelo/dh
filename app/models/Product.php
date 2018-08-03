<?php

use Phalcon\Mvc\Model;

class Product extends Model {

    public function initialize() {
        $this->setSource('product');
    }

    public static function createOne($source_url, $source_product_id, $source_product_name, $source_img, $product_data, $dh_product_id = '', $status = 0) {
        $model = new self();
        $model->source_url = $source_url;
        $model->source_product_id = $source_product_id;
        $model->source_product_name = $source_product_name;
        $model->source_img = $source_img;
        $model->product_data = $product_data;
        $model->dh_product_id = $dh_product_id;
        $model->status = $status;
        $model->createtime = date('Y-m-d H:i:s');
        if ($model->save()) {
            return $model;
        } else {
            return false;
        }
    }

    public static function getPage($page = 1, $size = 100, $status = '') {
        $q = [
            'columns' => 'id,source_url,source_product_id,source_product_name,source_img,dh_product_id,status,createtime'
        ];
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

}
