<?php
namespace RESTling;

abstract class Model {
    protected $data;

    public function __construct(){}

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
