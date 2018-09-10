<?php

use Phalcon\Mvc\Model;

class Imgs extends Model {

    public function initialize() {
        $this->setSource('imgs');
    }

    public static function createOne($source_img_url, $filename, $path, $img_data, $username) {
        $model = \Imgs::findFirst([
                    'conditions' => 'filename=:filename: and username=:username:',
                    'bind' => [
                        'filename' => $filename,
                        'username' => $username
                    ]
        ]);
        if ($model == false) {
            $model = new self();
        }
        $model->source_img_url = $source_img_url;
        $model->filename = $filename;
        $model->username = $username;
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
