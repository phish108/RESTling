<?php

namespace RESTling\Output;

class JSON extends Base {

    public function __construct() {
        $this->contentType = "application/json";
    }

    public function send($data) {

        if(is_array($data) || is_object($data)) {
            echo(json_encode($data));
        }
        else {
            parent::send($data);
        }
    }

    public function finish() {}
}

?>