<?php

use Phalcon\Mvc\Model;

class Queue extends Model {

    public function initialize() {
        $this->setSource('queue');
    }

}
