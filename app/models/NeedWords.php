<?php

use Phalcon\Mvc\Model;

class NeedWords extends Model {

    public function initialize() {
        $this->setSource('need_words');
    }

}
