<?php

use Phalcon\Mvc\Model;

class Cookies extends Model {

    public function initialize() {
        $this->setSource('cookies');
    }

}
