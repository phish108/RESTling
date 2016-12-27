<?php
namespace RESTling\Interfaces;

interface Security extends Validator {
    public function verify($model, $input);
    public function setScopes($scopeSet);
}
?>
