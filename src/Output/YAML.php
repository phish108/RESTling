<?php

namespace RESTling\Output;

class YAML extends Base {

    public function __construct() {
        $this->contentType = "text/yaml";
    }

    public function send($data) {

        if(is_array($data) || is_object($data)) {
            echo(\yaml_emit($data));
        }
        else {
            parent::send($data);
        }
    }

    public function finish() {}
}

?>