<?php

namespace RESTling\Input;

class JSON extends \RESTling\Input {

    public function parse() {
        $data = trim(file_get_contents("php://input"));

        if (empty($data)) {
            throw new Exception("Empty_Input_Data");
        }
        try {
            $this->bodyParameters = json_decode($data, true);
        }
        catch (Exception $err) {
            throw new Exception("Broken_Input_Data");
        }
        return "";
    }
}

?>
