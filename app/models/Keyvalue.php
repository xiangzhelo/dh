<?php

use Phalcon\Mvc\Model;

class Keyvalue extends Model {

    public function initialize() {
        $this->setSource('keyvalue');
    }

}
