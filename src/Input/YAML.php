<?php

namespace RESTling\Input;

class YAML extends Base {

    public function parse() {
        $data = trim(file_get_contents("php://input"));

        if (empty($data)) {
            return "Empty_Input_Data";
        }
        try {
            $this->bodyParameters = \yaml_parse($data);
        }
        catch (Exception $err) {
            return "Broken_Input_Data";
        }
        return "";
    }
}

?>