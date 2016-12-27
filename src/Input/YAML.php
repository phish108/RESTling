<?php

namespace RESTling\Input;

class YAML extends \RESTling\Input {

    public function parse() {
        $data = trim(file_get_contents("php://input"));

        if (empty($data)) {
            throw new \RESTling\Exception\EmptyInputData();
        }
        try {
            $this->bodyParameters = \yaml_parse($data);
        }
        catch (Exception $err) {
            throw new \RESTling\Exception\BrokenInput();
        }
        return "";
    }
}

?>
