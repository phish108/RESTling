<?php
namespace RESTling;

class Exception extends \Exception{
    public function __construct() {
        $cls = explode('\\', get_class($this)); // actual classname
        $mName = array_pop($cls);
        $message = join(" ", preg_split('/(?=[A-Z])/',$mName));
        parent::__construct($message, 1);
    }
}
?>
