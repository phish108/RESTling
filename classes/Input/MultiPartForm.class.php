<?php

namespace RESTling\Input;

class MultiPartForm extends Base {

    public function parse() {

        if (empty($_POST)) {
            return "Empty_Input_Data";
        }

        $this->bodyParameters = $_POST;

        return "";
    }
}

?>