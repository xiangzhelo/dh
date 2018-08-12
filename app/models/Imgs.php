<?php

use Phalcon\Mvc\Model;

class Imgs extends Model {

    public function initialize() {
        $this->setSource('imgs');
    }

    public static function createOne($source_img_url, $filename, $path, $img_data) {
        $model = \Imgs::findFirst([
                    'conditions' => 'filename=:filename:',
                    'bind' => [
                        'filename' => $filename
                    ]
        ]);
        if ($model == false) {
            $model = new self();
        }
        $model->source_img_url = $source_img_url;
        $model->filename = $filename;
        $model->path = $path;
        $model->img_data = $img_data;
        $model->createtime = date('Y-m-d H:i:s');
        if ($model->save()) {
            return $model;
        } else {
            return false;
        }
    }

}
