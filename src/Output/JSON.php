<?php

namespace RESTling\Output;

class JSON extends \RESTling\Output {

    public function __construct() {
        $this->contentType = "application/json";
    }

    public function setContentType($ct) {
    }

    public function data($data, $seperator=",") {
        if(is_array($data) || is_object($data)) {
            $data = json_encode($data);
        }
        parent::data($data, $seperator);
    }

    protected function formatTraceback() {
        // TODO fancy formating using an error model
        $this->data($this->traceback);
    }

    public function start($data = "[") {
        parent::start($data);
    }
    public function end($data = "]") {
        parent::end($data);
    }
}

?>
