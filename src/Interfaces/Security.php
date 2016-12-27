<?php
namespace RESTling\Interfaces;

interface Security {
    public function validate($model, $input);
    public function verify($model, $input);
    public function passes();
    public function setScopes($scopeSet);
}
?>
