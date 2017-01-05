<?php

namespace RESTling\Input;

class MultiPartForm extends \RESTling\Input {

    public function parse($data="") {

        if (empty($_POST)) {
            throw new \RESTling\Exception\EmptyInputData();
        }

        $this->bodyParameters = $_POST;

        return "";
    }
}

?>
