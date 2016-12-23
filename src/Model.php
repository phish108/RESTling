<?php
namespace RESTling;

abstract class Model {
    protected $data;
    protected $input;

    public function __construct(){
    }

    public function setInput($inputObject) {
        $this->input = $inputObject;
    }

    public function getHeaders() {
        return [];
    }
    public function getAllErrors() {
        return [];
    }

    public function hasData()
    {
        return !empty($this->data);
    }

    public function getData()
    {
        $d = $this->data;
        $this->data = null;

        return $d;
    }
}

?>
