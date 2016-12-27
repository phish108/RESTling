<?php
namespace RESTling\Interfaces;

interface Validator {
    public function validate($model, $input);
    public function passes();
}

?>
