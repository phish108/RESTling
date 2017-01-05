<?php

namespace RESTling\Input;

class XML extends \RESTling\Input {

    public function parse($data="") {
        throw new \RESTling\Exception\NotSupported();
    }
}

?>
