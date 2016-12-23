<?php

namespace RESTling\Input;

class JSON extends Base {

    public function parse() {
        $data = trim(file_get_contents("php://input"));

        if (empty($data)) {
            return "Empty_Input_Data";
        }
        try {
            $this->bodyParameters = json_decode($data, true);
        }
        catch (Exception $err) {
            return "Broken_Input_Data";
        }
        return "";
    }
}

?>