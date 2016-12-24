<?php

namespace RESTling\Input;

class MultiPartForm extends \RESTling\Input {

    public function parse() {

        if (empty($_POST)) {
            throw new Exception("Empty_Input_Data");
        }

        $this->bodyParameters = $_POST;

        return "";
    }
}

?>
