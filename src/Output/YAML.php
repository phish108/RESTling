<?php

namespace RESTling\Output;

class YAML extends \RESTling\Output\JSON {

    public function __construct() {
        $this->contentType = "text/yaml";
    }
    
    public function data($data) {
        if(is_array($data) || is_object($data)) {
            $data = \yaml_emit($data);
        }
        parent::data($data);
    }
}

?>
