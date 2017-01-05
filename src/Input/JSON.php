<?php

namespace RESTling\Input;

class JSON extends \RESTling\Input {

    public function parse($data="") {
        if (empty($data)) {
            $data = trim(file_get_contents("php://input"));
        }
        
        if (empty($data)) {
            throw new \RESTling\Exception\EmptyInputData();
        }
        try {
            $this->bodyParameters = json_decode($data, true);
        }
        catch (Exception $err) {
            throw new \RESTling\Exception\BrokenInput();
        }
        return "";
    }
}

?>
