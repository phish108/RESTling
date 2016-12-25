<?php
namespace RESTling;

interface SecurityInterface {
    public function validate($model, $input);
    public function verify($model, $input);
    public function passes();
    public function setScopes($scopeSet);
}
?>
