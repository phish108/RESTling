<?php
namespace RESTling;

/**
 * Internal exception handler.
 *
 * Generates the exception message from the exception class name.
 *
 * @return \RESTling\Exception
 */
class Exception extends \Exception{
    public function __construct() {
        $cls = explode('\\', get_class($this)); // actual classname
        $mName = array_pop($cls);
        $message = join(" ", preg_split('/(?=[A-Z])/',$mName));
        parent::__construct($message, 1);
    }
}

?>
