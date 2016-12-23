<?php
namespace RESTling;

interface Model {
    public function getHeaders();
    public function hasData();
    public function getData();
    public function getAllErrors();
}

?>
