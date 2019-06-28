<?php

use Phalcon\Mvc\Model;

class Log extends Model {

    public function initialize() {
        $this->setSource('log');
    }

    public static function createOne($content, $type) {
        $model = new self();
        $model->content = $content;
        $model->type = $type;
        $model->createtime = date('Y-m-d H:i:s');
        if ($model->save()) {
            return $model;
        } else {
            return false;
        }
    }

}
