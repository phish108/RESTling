<?php

namespace RESTling\Input;

class XML extends \RESTling\Input {

    public function parse() {
        throw new \RESTling\Exception\NotSupported();
    }
}

?>
